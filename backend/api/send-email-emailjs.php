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
        $pdf_base64 = null;
        if (empty($data->pdf_base64)) {
            if (!empty($reservation['pdf_path'])) {
                $pdf_path = '../storage/tickets/' . $reservation['pdf_path'];
                if (file_exists($pdf_path)) {
                    $pdf_base64 = base64_encode(file_get_contents($pdf_path));
                    error_log("PDF récupéré depuis le stockage");
                } else {
                    error_log("Fichier PDF non trouvé: " . $pdf_path);
                }
            }
        } else {
            $pdf_base64 = $data->pdf_base64;
        }
        
        $match_date = new DateTime($reservation['date_match']);
        
        // Vérifier les champs requis avant l'envoi
        $required_fields = ['email_client', 'nom_client'];
        foreach ($required_fields as $field) {
            if (empty($reservation[$field])) {
                error_log("Champ requis manquant: $field");
                http_response_code(400);
                echo json_encode(array("message" => "Données manquantes: $field"));
                exit;
            }
        }
        
        // Préparer les données pour EmailJS (structure simplifiée sans PDF d'abord)
// Générer l'URL de téléchargement
$download_url = 'http://localhost/Billet/backend/storage/tickets/' . $reservation['pdf_path'];

// Préparer les données pour EmailJS
$template_params = array(
    'to_email' => $reservation['email_client'],
    'to_name' => $reservation['nom_client'],
    'match_title' => $reservation['equipe_domicile'] . ' vs ' . $reservation['equipe_exterieur'],
    'equipe_domicile' => $reservation['equipe_domicile'],
    'equipe_exterieur' => $reservation['equipe_exterieur'],
    'match_date' => $match_date->format('d/m/Y à H:i'),
    'stadium_name' => $reservation['stade_nom'],
    'stadium_city' => $reservation['stade_ville'],
    'ticket_category' => $reservation['categorie'] ?? 'Standard',
    'ticket_quantity' => $reservation['nombre_billets'],
    'total_price' => $reservation['prix_total'] . ' DH',
    'reservation_id' => $reservation['id'],
    'download_url' => $download_url, // Lien de téléchargement
    'message' => 'Voici vos billets électroniques pour le match ' . 
                 $reservation['equipe_domicile'] . ' vs ' . $reservation['equipe_exterieur'] . 
                 '. Présentez ces billets à l\'entrée du stade.'
);
        
        $email_data = array(
            'service_id' => 'service_38yzkmj',
            'template_id' => 'template_ekbnhow',
            'user_id' => 'jeFYu34Sxj8CbAE2c',
            'template_params' => $template_params
        );
        
        // Ajouter le PDF seulement si pas trop volumineux (limite EmailJS ~3MB)
        if ($pdf_base64) {
            $pdf_size = strlen($pdf_base64);
            error_log("Taille du PDF en base64: " . $pdf_size . " bytes");
            
            if ($pdf_size < 3000000) { // Limite de 3MB
                $email_data['template_params']['pdf_attachment'] = $pdf_base64;
                $email_data['template_params']['pdf_name'] = 'billet_' . $reservation['id'] . '.pdf';
                error_log("PDF ajouté à l'email");
            } else {
                error_log("PDF trop volumineux pour EmailJS, envoi sans pièce jointe");
                $email_data['template_params']['message'] .= ' Le PDF des billets sera disponible dans votre espace client.';
            }
        }
        
        error_log("Données préparées pour EmailJS");
        error_log("Email destinataire: " . $reservation['email_client']);
        error_log("Données JSON à envoyer: " . json_encode($email_data, JSON_PRETTY_PRINT));
        
        // Test de la validité JSON
        $json_data = json_encode($email_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erreur d'encodage JSON: " . json_last_error_msg());
            http_response_code(500);
            echo json_encode(array("message" => "Erreur d'encodage des données."));
            exit;
        }
        
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
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'User-Agent: Mozilla/5.0 (compatible; PHP-EmailJS/1.0)'
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        $curl_info = curl_getinfo($curl);
        curl_close($curl);
        
        error_log("Réponse EmailJS - Code HTTP: $http_code");
        error_log("Réponse EmailJS - Contenu: " . $response);
        error_log("Infos cURL: " . print_r($curl_info, true));
        
        if ($curl_error) {
            error_log("Erreur cURL: " . $curl_error);
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            // Marquer l'email comme envoyé dans la base de données
            $update_query = "UPDATE reservations SET email_sent = 1, email_sent_at = NOW() WHERE id = :reservation_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':reservation_id', $reservation['id']);
            
            if ($update_stmt->execute()) {
                error_log("Email marqué comme envoyé dans la base de données");
                http_response_code(200);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Email envoyé avec succès.",
                    "email_sent_to" => $reservation['email_client'],
                    "reservation_id" => $reservation['id']
                ));
            } else {
                error_log("Erreur lors de la mise à jour de la réservation: " . implode(" ", $update_stmt->errorInfo()));
                http_response_code(200); // Email envoyé mais problème DB
                echo json_encode(array(
                    "success" => true,
                    "message" => "Email envoyé mais erreur lors de la mise à jour de la base de données.",
                    "warning" => "Réservation non marquée comme envoyée"
                ));
            }
        } else {
            // Analyser la réponse d'erreur
            $error_response = json_decode($response, true);
            $error_message = "Erreur inconnue";
            
            if ($error_response && isset($error_response['message'])) {
                $error_message = $error_response['message'];
            } elseif ($error_response && isset($error_response['error'])) {
                $error_message = $error_response['error'];
            } elseif ($curl_error) {
                $error_message = $curl_error;
            }
            
            error_log("Erreur EmailJS: " . $error_message . " (Code: $http_code)");
            
            http_response_code(500);
            echo json_encode(array(
                "success" => false,
                "message" => "Erreur lors de l'envoi de l'email.",
                "error" => $error_message,
                "http_code" => $http_code,
                "debug_info" => array(
                    "service_id" => 'service_38yzkmj',
                    "template_id" => 'template_ekbnhow',
                    "recipient" => $reservation['email_client'],
                    "has_pdf" => !empty($pdf_base64)
                )
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