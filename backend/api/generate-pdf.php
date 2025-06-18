<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../vendor/autoload.php';
include_once '../config/database.php';
include_once 'authMiddleware.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use chillerlan\QRCode\QRCode;

// Activer le logging
error_log("Début de la génération PDF");

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auth = new AuthMiddleware();
    $user_data = $auth->authenticateRequest();
    
    if (!$user_data) {
        http_response_code(401);
        echo json_encode(array("message" => "Token invalide."));
        error_log("Token invalide");
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->reservation_id)) {
        error_log("Traitement de la réservation ID: " . $data->reservation_id);
        
        // Récupérer les détails de la réservation
        $query = "SELECT r.*, 
                    ed.nom as equipe_domicile, 
                    ee.nom as equipe_exterieur,
                    m.date_match,
                    s.nom as stade_nom,
                    s.ville as stade_ville,
                    s.adresse as stade_adresse,
                    u.nom as user_nom
                  FROM reservations r
                  JOIN matchs m ON r.match_id = m.id
                  JOIN equipes ed ON m.equipe_domicile_id = ed.id
                  JOIN equipes ee ON m.equipe_exterieur_id = ee.id
                  JOIN stades s ON m.stade_id = s.id
                  JOIN users u ON r.user_id = u.id
                  WHERE r.id = :reservation_id AND r.user_id = :user_id AND r.statut IN ('confirme', 'en_attente')";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':reservation_id', $data->reservation_id);
        $stmt->bindParam(':user_id', $user_data['user_id']);
        
        if (!$stmt->execute()) {
            error_log("Erreur SQL: " . implode(" ", $stmt->errorInfo()));
            http_response_code(500);
            echo json_encode(array("message" => "Erreur de base de données."));
            exit;
        }
        
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            error_log("Réservation non trouvée ou non confirmée");
            http_response_code(404);
            echo json_encode(array("message" => "Réservation non trouvée ou non confirmée."));
            exit;
        }

        // Si la réservation est en attente, la confirmer
        if ($reservation['statut'] == 'en_attente') {
            $update_stmt = $db->prepare("UPDATE reservations SET statut = 'confirme' WHERE id = :reservation_id");
            $update_stmt->bindParam(':reservation_id', $reservation['id']);
            if (!$update_stmt->execute()) {
                error_log("Erreur lors de la confirmation de la réservation");
                http_response_code(500);
                echo json_encode(array("message" => "Erreur lors de la confirmation de la réservation."));
                exit;
            }
        }

        // Générer les billets individuels
        $billets = [];
        for ($i = 1; $i <= $reservation['nombre_billets']; $i++) {
            $qr_data = json_encode([
                'reservation_id' => $reservation['id'],
                'billet_num' => $i,
                'match_id' => $reservation['match_id'],
                'categorie' => $reservation['categorie'],
                'timestamp' => time(),
                'security_hash' => md5($reservation['id'].$i.$reservation['match_id'].'secret_key')
            ]);
            
            try {
                $qr_code = (new QRCode)->render($qr_data);
                $billets[] = [
                    'numero' => $i,
                    'qr_code' => $qr_code,
                    'section' => $reservation['categorie'],
                    'rangee' => 'R' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'siege' => 'S' . str_pad($i, 3, '0', STR_PAD_LEFT)
                ];
            } catch (Exception $e) {
                error_log("Erreur génération QR code: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(array("message" => "Erreur lors de la génération du QR code."));
                exit;
            }
        }
        
        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('chroot', realpath('../'));
        
        $dompdf = new Dompdf($options);
        
        // Template HTML pour le billet
        $html = generateTicketHTML($reservation, $billets);
        
        try {
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Récupérer le contenu PDF
            $pdf_content = $dompdf->output();
            
            // Vérifier et créer le dossier de stockage
            $storage_dir = '../storage/tickets/';
            if (!file_exists($storage_dir)) {
                if (!mkdir($storage_dir, 0777, true)) {
                    throw new Exception("Impossible de créer le dossier de stockage");
                }
            }
            
            // Vérifier les permissions d'écriture
            if (!is_writable($storage_dir)) {
                throw new Exception("Le dossier n'est pas accessible en écriture");
            }
            
            // Sauvegarder le PDF
            $pdf_filename = 'billet_'.$reservation['id'].'_'.time().'.pdf';
            $pdf_path = $storage_dir . $pdf_filename;
            
            if (file_put_contents($pdf_path, $pdf_content)) {
                error_log("PDF sauvegardé avec succès: " . $pdf_path);
                
                // Mettre à jour la réservation avec le chemin du PDF
                $update_query = "UPDATE reservations SET pdf_path = :pdf_path WHERE id = :reservation_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':pdf_path', $pdf_filename);
                $update_stmt->bindParam(':reservation_id', $reservation['id']);
                
                if ($update_stmt->execute()) {
                    error_log("Réservation mise à jour avec le chemin PDF");
                    
                    // Enregistrer les billets individuels dans la table billets
                    foreach ($billets as $billet) {
                        $billet_query = "INSERT INTO billets 
                            (reservation_id, numero_billet, section, rangee, siege, qr_code, statut)
                            VALUES (:reservation_id, :numero_billet, :section, :rangee, :siege, :qr_code, 'valide')";
                        $billet_stmt = $db->prepare($billet_query);
                        $billet_stmt->execute([
                            ':reservation_id' => $reservation['id'],
                            ':numero_billet' => 'B'.$reservation['id'].'-'.$billet['numero'],
                            ':section' => $billet['section'],
                            ':rangee' => $billet['rangee'],
                            ':siege' => $billet['siege'],
                            ':qr_code' => $qr_data
                        ]);
                    }
                    
                    http_response_code(200);
                    echo json_encode(array(
                        "message" => "Billet PDF généré avec succès.",
                        "pdf_url" => "http://localhost/Billet/backend/storage/tickets/" . $pdf_filename,
                        "pdf_base64" => base64_encode($pdf_content),
                        "reservation_id" => $reservation['id'],
                        "qr_codes" => array_map(function($b) { return $b['qr_code']; }, $billets)
                    ));
                } else {
                    throw new Exception("Erreur lors de la mise à jour de la réservation");
                }
            } else {
                throw new Exception("Erreur lors de l'écriture du fichier PDF");
            }
        } catch (Exception $e) {
            error_log("Erreur DomPDF: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(array(
                "message" => "Erreur lors de la génération du PDF.",
                "error" => $e->getMessage()
            ));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID de réservation requis."));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}

function generateTicketHTML($reservation, $billets) {
    $match_date = new DateTime($reservation['date_match']);
    $date_formatted = $match_date->format('d/m/Y');
    $heure_formatted = $match_date->format('H:i');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .ticket { border: 2px solid #dc2626; margin-bottom: 30px; page-break-after: always; }
            .header { background: linear-gradient(135deg, #dc2626, #16a34a); color: white; padding: 20px; text-align: center; }
            .match-info { padding: 20px; background: #f9fafb; }
            .teams { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; }
            .team { text-align: center; flex: 1; }
            .vs { font-size: 24px; font-weight: bold; color: #dc2626; }
            .details { display: flex; justify-content: space-between; margin: 20px 0; }
            .detail-box { background: white; padding: 15px; border-radius: 8px; text-align: center; flex: 1; margin: 0 5px; }
            .qr-section { text-align: center; padding: 20px; border-top: 2px dashed #dc2626; }
            .qr-code { margin: 10px 0; }
            .footer { background: #374151; color: white; padding: 15px; font-size: 12px; }
            .seat-info { background: #fef3c7; padding: 15px; margin: 10px 0; border-radius: 8px; }
            h1 { margin: 0; font-size: 28px; }
            h2 { margin: 0; color: #dc2626; }
            h3 { margin: 10px 0; }
            .price { font-size: 24px; font-weight: bold; color: #16a34a; }
            .ticket-number { position: absolute; top: 10px; right: 10px; background: #dc2626; color: white; padding: 5px 10px; border-radius: 4px; }
        </style>
    </head>
    <body>';
    
    foreach ($billets as $billet) {
        $html .= '
        <div class="ticket">
            <div class="ticket-number">Billet #' . $billet['numero'] . '</div>
            <div class="header">
                <h1>🏆 BILLETTERIE FOOTBALL MAROC</h1>
                <p>Billet Électronique - Match Officiel</p>
            </div>
            
            <div class="match-info">
                <div class="teams">
                    <div class="team">
                        <h2>' . htmlspecialchars($reservation['equipe_domicile']) . '</h2>
                        <p>Équipe Domicile</p>
                    </div>
                    <div class="vs">VS</div>
                    <div class="team">
                        <h2>' . htmlspecialchars($reservation['equipe_exterieur']) . '</h2>
                        <p>Équipe Extérieur</p>
                    </div>
                </div>
                
                <div class="details">
                    <div class="detail-box">
                        <h3>📅 Date</h3>
                        <p>' . $date_formatted . '</p>
                        <p><strong>' . $heure_formatted . '</strong></p>
                    </div>
                    <div class="detail-box">
                        <h3>🏟️ Stade</h3>
                        <p><strong>' . htmlspecialchars($reservation['stade_nom']) . '</strong></p>
                        <p>' . htmlspecialchars($reservation['stade_ville']) . '</p>
                    </div>
                    <div class="detail-box">
                        <h3>💰 Prix</h3>
                        <p class="price">' . number_format($reservation['prix_total'] / $reservation['nombre_billets'], 2) . ' DH</p>
                        <p>Catégorie ' . htmlspecialchars($reservation['categorie']) . '</p>
                    </div>
                </div>
                
                <div class="seat-info">
                    <h3>🎫 Informations de Place</h3>
                    <div class="details">
                        <div class="detail-box">
                            <strong>Section</strong><br>' . htmlspecialchars($billet['section']) . '
                        </div>
                        <div class="detail-box">
                            <strong>Rangée</strong><br>' . htmlspecialchars($billet['rangee']) . '
                        </div>
                        <div class="detail-box">
                            <strong>Siège</strong><br>' . htmlspecialchars($billet['siege']) . '
                        </div>
                    </div>
                </div>
                
                <div style="background: #f3f4f6; padding: 15px; margin: 10px 0; border-radius: 8px;">
                    <h3>👤 Informations Client</h3>
                    <p><strong>Nom:</strong> ' . htmlspecialchars($reservation['nom_client']) . '</p>
                    <p><strong>Email:</strong> ' . htmlspecialchars($reservation['email_client']) . '</p>
                    <p><strong>Téléphone:</strong> ' . htmlspecialchars($reservation['telephone_client']) . '</p>
                </div>
            </div>
            
            <div class="qr-section">
                <h3>Code QR - Scan à l\'entrée</h3>
                <div class="qr-code">
                    <img src="' . $billet['qr_code'] . '" alt="QR Code" style="width: 150px; height: 150px;">
                </div>
                <p><strong>ID Réservation:</strong> #' . $reservation['id'] . '</p>
                <p><strong>Billet #:</strong> ' . $billet['numero'] . '/' . $reservation['nombre_billets'] . '</p>
            </div>
            
            <div class="footer">
                <h3>📋 Instructions Importantes</h3>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Présentez ce billet (imprimé ou sur mobile) à l\'entrée du stade</li>
                    <li>Arrivez au moins 30 minutes avant le début du match</li>
                    <li>Pièce d\'identité obligatoire pour l\'accès au stade</li>
                    <li>Ce billet est personnel et non transférable</li>
                    <li>Aucun remboursement en cas d\'annulation du match</li>
                </ul>
                
                <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #6b7280;">
                    <p><strong>Adresse du Stade:</strong> ' . htmlspecialchars($reservation['stade_adresse']) . '</p>
                    <p>🌐 www.billetterie-football.ma | 📞 +212 5XX XXX XXX</p>
                    <p style="font-size: 10px; color: #9ca3af;">Généré le ' . date('d/m/Y à H:i') . ' - Billet électronique officiel</p>
                </div>
            </div>
        </div>';
    }
    
    $html .= '</body></html>';
    return $html;
}
?>