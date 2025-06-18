<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';
include_once 'authMiddleware.php';

// Activer le logging
error_log("Début de l'envoi d'email");

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
        error_log("Traitement de l'envoi d'email pour la réservation ID: " . $data->reservation_id);
        
        // Récupérer les détails de la réservation
        $query = "SELECT r.*, 
                    ed.nom as equipe_domicile, 
                    ee.nom as equipe_exterieur,
                    m.date_match,
                    s.nom as stade_nom,
                    s.ville as stade_ville,
                    u.nom as user_nom,
                    u.email as user_email
                  FROM reservations r
                  JOIN matchs m ON r.match_id = m.id
                  JOIN equipes ed ON m.equipe_domicile_id = ed.id
                  JOIN equipes ee ON m.equipe_exterieur_id = ee.id
                  JOIN stades s ON m.stade_id = s.id
                  JOIN users u ON r.user_id = u.id
                  WHERE r.id = :reservation_id AND r.user_id = :user_id";
        
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
            error_log("Réservation non trouvée");
            http_response_code(404);
            echo json_encode(array("message" => "Réservation non trouvée."));
            exit;
        }
        
// Si le PDF n'est pas fourni dans la requête, le récupérer depuis le stockage
if (empty($data->pdf_base64)) {
    if (!empty($reservation['pdf_path'])) {
        $pdf_path = '../storage/tickets/' . $reservation['pdf_path'];
        if (file_exists($pdf_path)) {
            $data->pdf_base64 = base64_encode(file_get_contents($pdf_path));
        } else {
            error_log("Fichier PDF non trouvé: " . $pdf_path);
        }
    }
}
        $match_date = new DateTime($reservation['date_match']);
        
        // Préparer les données pour EmailJS
        $email_data = array(
            'service_id' => 'service_38yzkmj',
            'template_id' => 'template_ekbnhow',
            'user_id' => 'jeFYu34Sxj8CbAE2c',
            'template_params' => array(
                'to_email' => $reservation['email_client'],
                'to_name' => $reservation['nom_client'],
                'match_title' => $reservation['equipe_domicile'] . ' vs ' . $reservation['equipe_exterieur'],
                'match_date' => $match_date->format('d/m/Y à H:i'),
                'stadium_name' => $reservation['stade_nom'],
                'stadium_city' => $reservation['stade_ville'],
                'ticket_category' => $reservation['categorie'],
                'ticket_quantity' => $reservation['nombre_billets'],
                'total_price' => $reservation['prix_total'] . ' DH',
                'reservation_id' => $reservation['id'],
                'pdf_attachment' => $data->pdf_base64 ?? null,
                'message' => 'Voici vos billets électroniques pour le match ' . 
                             $reservation['equipe_domicile'] . ' vs ' . $reservation['equipe_exterieur'] . 
                             '. Présentez ces billets à l\'entrée du stade.'
            ),
            'accessToken' => '' // Ajoutez votre token d'accès si nécessaire
        );
        
        error_log("Envoi des données à EmailJS: " . json_encode($email_data));
        
        // Envoyer l'email via EmailJS API
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.emailjs.com/api/v1.0/email/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($email_data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer jeFYu34Sxj8CbAE2c' // Ajout de l'autorisation
            ),
        ));
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        error_log("Réponse EmailJS - Code: $http_code - Réponse: $response");
        
        if ($http_code == 200) {
            // Marquer l'email comme envoyé dans la base de données
            $update_query = "UPDATE reservations SET email_sent = 1, email_sent_at = NOW() WHERE id = :reservation_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':reservation_id', $reservation['id']);
            
            if ($update_stmt->execute()) {
                error_log("Email marqué comme envoyé dans la base de données");
                http_response_code(200);
                echo json_encode(array(
                    "message" => "Email envoyé avec succès.",
                    "email_response" => json_decode($response, true)
                ));
            } else {
                error_log("Erreur lors de la mise à jour de la réservation");
                http_response_code(500);
                echo json_encode(array(
                    "message" => "Email envoyé mais erreur lors de la mise à jour de la base de données.",
                    "error" => implode(" ", $update_stmt->errorInfo())
                ));
            }
        } else {
            error_log("Erreur EmailJS: $curl_error - Code: $http_code");
            http_response_code(500);
            echo json_encode(array(
                "message" => "Erreur lors de l'envoi de l'email.",
                "error" => $curl_error ?: $response,
                "http_code" => $http_code
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
?>