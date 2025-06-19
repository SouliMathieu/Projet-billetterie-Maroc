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

// Fonction de logging améliorée
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message);
}

function logInfo($message) {
    error_log(date('[Y-m-d H:i:s] INFO: ') . $message);
}

// Activer le logging
logInfo("Début de la génération PDF");

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Erreur de connexion à la base de données");
    }
} catch (Exception $e) {
    logError("Erreur connexion DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("message" => "Erreur de connexion à la base de données.", "error" => $e->getMessage()));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $auth = new AuthMiddleware();
        $user_data = $auth->authenticateRequest();
        
        if (!$user_data) {
            http_response_code(401);
            echo json_encode(array("message" => "Token invalide."));
            logError("Token invalide");
            exit;
        }
        
        $input = file_get_contents("php://input");
        logInfo("Input reçu: " . $input);
        
        $data = json_decode($input);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erreur de décodage JSON: " . json_last_error_msg());
        }
        
        if (empty($data->reservation_id)) {
            http_response_code(400);
            echo json_encode(array("message" => "ID de réservation requis."));
            exit;
        }
        
        logInfo("Traitement de la réservation ID: " . $data->reservation_id);
        
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
        $stmt->bindParam(':reservation_id', $data->reservation_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_data['user_id'], PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            logError("Erreur SQL: " . implode(" ", $errorInfo));
            throw new Exception("Erreur de requête SQL: " . $errorInfo[2]);
        }
        
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            logError("Réservation non trouvée pour ID: " . $data->reservation_id . " et user_id: " . $user_data['user_id']);
            http_response_code(404);
            echo json_encode(array("message" => "Réservation non trouvée ou non confirmée."));
            exit;
        }
        
        logInfo("Réservation trouvée: " . json_encode($reservation));

        // Si la réservation est en attente, la confirmer
        if ($reservation['statut'] == 'en_attente') {
            $update_stmt = $db->prepare("UPDATE reservations SET statut = 'confirme' WHERE id = :reservation_id");
            $update_stmt->bindParam(':reservation_id', $reservation['id'], PDO::PARAM_INT);
            if (!$update_stmt->execute()) {
                logError("Erreur lors de la confirmation de la réservation");
                throw new Exception("Erreur lors de la confirmation de la réservation.");
            }
            logInfo("Réservation confirmée");
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
                // Vérifier si la classe QRCode existe
                if (!class_exists('chillerlan\QRCode\QRCode')) {
                    throw new Exception("La bibliothèque QRCode n'est pas disponible");
                }
                
                $qrcode = new QRCode();
                $qr_code = $qrcode->render($qr_data);
                
                $billets[] = [
                    'numero' => $i,
                    'qr_code' => $qr_code,
                    'section' => $reservation['categorie'],
                    'rangee' => 'R' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'siege' => 'S' . str_pad($i, 3, '0', STR_PAD_LEFT)
                ];
                
                logInfo("QR Code généré pour billet " . $i);
            } catch (Exception $e) {
                logError("Erreur génération QR code pour billet $i: " . $e->getMessage());
                // Continuer sans QR code si nécessaire
                $billets[] = [
                    'numero' => $i,
                    'qr_code' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', // Image vide
                    'section' => $reservation['categorie'],
                    'rangee' => 'R' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'siege' => 'S' . str_pad($i, 3, '0', STR_PAD_LEFT)
                ];
            }
        }
        
        logInfo("Billets générés: " . count($billets));
        
        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false); // Désactiver PHP pour la sécurité
        $options->set('tempDir', sys_get_temp_dir());
        
        // Définir un chroot sûr
        $chroot_path = realpath('../');
        if ($chroot_path) {
            $options->set('chroot', $chroot_path);
        }
        
        $dompdf = new Dompdf($options);
        
        // Template HTML pour le billet
        $html = generateTicketHTML($reservation, $billets);
        
        logInfo("HTML généré, taille: " . strlen($html) . " caractères");
        
        try {
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Récupérer le contenu PDF
            $pdf_content = $dompdf->output();
            
            logInfo("PDF généré, taille: " . strlen($pdf_content) . " bytes");
            
            // Vérifier et créer le dossier de stockage
            $storage_dir = '../storage/tickets/';
            if (!file_exists($storage_dir)) {
                if (!mkdir($storage_dir, 0755, true)) {
                    throw new Exception("Impossible de créer le dossier de stockage: " . $storage_dir);
                }
                logInfo("Dossier de stockage créé: " . $storage_dir);
            }
            
            // Vérifier les permissions d'écriture
            if (!is_writable($storage_dir)) {
                throw new Exception("Le dossier n'est pas accessible en écriture: " . $storage_dir);
            }
            
            // Sauvegarder le PDF
            $pdf_filename = 'billet_'.$reservation['id'].'_'.time().'.pdf';
            $pdf_path = $storage_dir . $pdf_filename;
            
            if (file_put_contents($pdf_path, $pdf_content) === false) {
                throw new Exception("Erreur lors de l'écriture du fichier PDF: " . $pdf_path);
            }
            
            logInfo("PDF sauvegardé avec succès: " . $pdf_path);
            
            // Mettre à jour la réservation avec le chemin du PDF
            $update_query = "UPDATE reservations SET pdf_path = :pdf_path WHERE id = :reservation_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':pdf_path', $pdf_filename);
            $update_stmt->bindParam(':reservation_id', $reservation['id'], PDO::PARAM_INT);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Erreur lors de la mise à jour de la réservation avec le chemin PDF");
            }
            
            logInfo("Réservation mise à jour avec le chemin PDF");
            
            // Enregistrer les billets individuels dans la table billets
            foreach ($billets as $billet) {
                try {
                    // Vérifier si la table billets existe
                    $check_table = $db->query("SHOW TABLES LIKE 'billets'");
                    if ($check_table->rowCount() > 0) {
                        $qr_data_for_db = json_encode([
                            'reservation_id' => $reservation['id'],
                            'billet_num' => $billet['numero'],
                            'match_id' => $reservation['match_id'],
                            'categorie' => $reservation['categorie'],
                            'timestamp' => time(),
                            'security_hash' => md5($reservation['id'].$billet['numero'].$reservation['match_id'].'secret_key')
                        ]);
                        
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
                            ':qr_code' => $qr_data_for_db
                        ]);
                    }
                } catch (Exception $e) {
                    logError("Erreur lors de l'enregistrement du billet " . $billet['numero'] . ": " . $e->getMessage());
                    // Continuer même si l'enregistrement des billets échoue
                }
            }
            
            http_response_code(200);
            echo json_encode(array(
                "message" => "Billet PDF généré avec succès.",
                "pdf_url" => "http://localhost/Billet/backend/storage/tickets/" . $pdf_filename,
                "pdf_base64" => base64_encode($pdf_content),
                "reservation_id" => $reservation['id'],
                "filename" => $pdf_filename
            ));
            
        } catch (Exception $e) {
            logError("Erreur DomPDF: " . $e->getMessage());
            throw new Exception("Erreur lors de la génération du PDF: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        logError("Erreur générale: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(array(
            "message" => "Erreur lors du traitement de la demande.",
            "error" => $e->getMessage(),
            "trace" => $e->getTraceAsString()
        ));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}

function generateTicketHTML($reservation, $billets) {
    try {
        $match_date = new DateTime($reservation['date_match']);
        $date_formatted = $match_date->format('d/m/Y');
        $heure_formatted = $match_date->format('H:i');
    } catch (Exception $e) {
        error_log("Erreur formatage date: " . $e->getMessage());
        $date_formatted = date('d/m/Y', strtotime($reservation['date_match']));
        $heure_formatted = date('H:i', strtotime($reservation['date_match']));
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 20px; }
            body { 
                font-family: "DejaVu Sans", Arial, sans-serif; 
                margin: 0; 
                padding: 0;
                font-size: 12px;
                line-height: 1.4;
            }
            .ticket { 
                border: 2px solid #dc2626; 
                margin-bottom: 30px; 
                page-break-after: always;
                position: relative;
            }
            .header { 
                background: #dc2626; 
                color: white; 
                padding: 20px; 
                text-align: center; 
            }
            .match-info { 
                padding: 20px; 
                background: #f9fafb; 
            }
            .teams { 
                text-align: center;
                margin: 20px 0; 
            }
            .team { 
                display: inline-block;
                margin: 0 10px;
            }
            .vs { 
                font-size: 18px; 
                font-weight: bold; 
                color: #dc2626; 
                margin: 0 20px;
            }
            .details { 
                margin: 20px 0; 
            }
            .detail-box { 
                background: white; 
                padding: 10px; 
                margin: 10px 0;
                border: 1px solid #e5e7eb;
                text-align: center; 
            }
            .qr-section { 
                text-align: center; 
                padding: 20px; 
                border-top: 2px dashed #dc2626; 
            }
            .qr-code { 
                margin: 10px 0; 
            }
            .footer { 
                background: #374151; 
                color: white; 
                padding: 15px; 
                font-size: 10px; 
            }
            .seat-info { 
                background: #fef3c7; 
                padding: 15px; 
                margin: 10px 0; 
            }
            h1 { 
                margin: 0; 
                font-size: 20px; 
            }
            h2 { 
                margin: 5px 0; 
                color: #dc2626; 
                font-size: 16px;
            }
            h3 { 
                margin: 10px 0 5px 0; 
                font-size: 14px;
            }
            .price { 
                font-size: 16px; 
                font-weight: bold; 
                color: #16a34a; 
            }
            .ticket-number { 
                position: absolute; 
                top: 10px; 
                right: 10px; 
                background: #dc2626; 
                color: white; 
                padding: 5px 10px; 
                font-size: 12px;
                font-weight: bold;
            }
            ul {
                margin: 5px 0;
                padding-left: 15px;
            }
            li {
                margin: 3px 0;
            }
        </style>
    </head>
    <body>';
    
    foreach ($billets as $billet) {
        $prix_unitaire = isset($reservation['prix_total']) && $reservation['nombre_billets'] > 0 
            ? number_format($reservation['prix_total'] / $reservation['nombre_billets'], 2) 
            : '0.00';
            
        $html .= '
        <div class="ticket">
            <div class="ticket-number">Billet #' . htmlspecialchars($billet['numero']) . '</div>
            <div class="header">
                <h1>🏆 BILLETTERIE FOOTBALL MAROC</h1>
                <p>Billet Électronique - Match Officiel</p>
            </div>
            
            <div class="match-info">
                <div class="teams">
                    <div class="team">
                        <h2>' . htmlspecialchars($reservation['equipe_domicile'] ?? 'Équipe A') . '</h2>
                        <p>Équipe Domicile</p>
                    </div>
                    <span class="vs">VS</span>
                    <div class="team">
                        <h2>' . htmlspecialchars($reservation['equipe_exterieur'] ?? 'Équipe B') . '</h2>
                        <p>Équipe Extérieur</p>
                    </div>
                </div>
                
                <div class="details">
                    <div class="detail-box">
                        <h3>📅 Date et Heure</h3>
                        <p><strong>' . $date_formatted . ' à ' . $heure_formatted . '</strong></p>
                    </div>
                    <div class="detail-box">
                        <h3>🏟️ Stade</h3>
                        <p><strong>' . htmlspecialchars($reservation['stade_nom'] ?? 'Stade Municipal') . '</strong></p>
                        <p>' . htmlspecialchars($reservation['stade_ville'] ?? 'Ville') . '</p>
                    </div>
                    <div class="detail-box">
                        <h3>💰 Prix</h3>
                        <p class="price">' . $prix_unitaire . ' DH</p>
                        <p>Catégorie ' . htmlspecialchars($reservation['categorie'] ?? 'Standard') . '</p>
                    </div>
                </div>
                
                <div class="seat-info">
                    <h3>🎫 Informations de Place</h3>
                    <div class="details">
                        <div class="detail-box">
                            <strong>Section:</strong> ' . htmlspecialchars($billet['section']) . '
                        </div>
                        <div class="detail-box">
                            <strong>Rangée:</strong> ' . htmlspecialchars($billet['rangee']) . '
                        </div>
                        <div class="detail-box">
                            <strong>Siège:</strong> ' . htmlspecialchars($billet['siege']) . '
                        </div>
                    </div>
                </div>
                
                <div style="background: #f3f4f6; padding: 15px; margin: 10px 0;">
                    <h3>👤 Informations Client</h3>
                    <p><strong>Nom:</strong> ' . htmlspecialchars($reservation['nom_client'] ?? $reservation['user_nom'] ?? 'Client') . '</p>
                    <p><strong>Email:</strong> ' . htmlspecialchars($reservation['email_client'] ?? 'email@example.com') . '</p>
                    <p><strong>Téléphone:</strong> ' . htmlspecialchars($reservation['telephone_client'] ?? 'N/A') . '</p>
                </div>
            </div>
            
            <div class="qr-section">
                <h3>Code QR - Scan à l\'entrée</h3>
                <div class="qr-code">
                    <img src="' . $billet['qr_code'] . '" alt="QR Code" style="width: 120px; height: 120px;">
                </div>
                <p><strong>ID Réservation:</strong> #' . $reservation['id'] . '</p>
                <p><strong>Billet:</strong> ' . $billet['numero'] . '/' . $reservation['nombre_billets'] . '</p>
            </div>
            
            <div class="footer">
                <h3>📋 Instructions Importantes</h3>
                <ul>
                    <li>Présentez ce billet (imprimé ou sur mobile) à l\'entrée du stade</li>
                    <li>Arrivez au moins 30 minutes avant le début du match</li>
                    <li>Pièce d\'identité obligatoire pour l\'accès au stade</li>
                    <li>Ce billet est personnel et non transférable</li>
                    <li>Aucun remboursement en cas d\'annulation du match</li>
                </ul>
                
                <div style="text-align: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #6b7280;">
                    <p><strong>Adresse:</strong> ' . htmlspecialchars($reservation['stade_adresse'] ?? 'Adresse du stade') . '</p>
                    <p>🌐 www.billetterie-football.ma | 📞 +212 5XX XXX XXX</p>
                    <p style="font-size: 9px; color: #9ca3af;">Généré le ' . date('d/m/Y à H:i') . ' - Billet électronique officiel</p>
                </div>
            </div>
        </div>';
    }
    
    $html .= '</body></html>';
    return $html;
}
?>