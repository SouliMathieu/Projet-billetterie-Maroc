<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';
include_once 'authMiddleware.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auth = new AuthMiddleware();
    $user_data = $auth->authenticateRequest();
    
    if (!$user_data) {
        http_response_code(401);
        echo json_encode(array("message" => "Token invalide."));
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"));
    
    if ($data->action == 'add_points' && !empty($data->reservation_id)) {
        // Récupérer les détails de la réservation
        $query = "SELECT * FROM reservations WHERE id = :reservation_id AND user_id = :user_id AND statut = 'confirme'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':reservation_id', $data->reservation_id);
        $stmt->bindParam(':user_id', $user_data['user_id']);
        $stmt->execute();
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            http_response_code(404);
            echo json_encode(array("message" => "Réservation non trouvée."));
            exit;
        }
        
        // Calculer les points (1 point par 10 DH dépensés)
        $points_earned = floor($reservation['prix_total'] / 10);
        
        // Ajouter les points à l'utilisateur
        $update_query = "UPDATE users SET points_fidelite = points_fidelite + :points WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':points', $points_earned);
        $update_stmt->bindParam(':user_id', $user_data['user_id']);
        $update_stmt->execute();
        
        // Enregistrer la transaction de points
        $transaction_query = "INSERT INTO loyalty_transactions (user_id, reservation_id, points, type, description) 
                             VALUES (:user_id, :reservation_id, :points, 'earned', :description)";
        $transaction_stmt = $db->prepare($transaction_query);
        $transaction_stmt->bindParam(':user_id', $user_data['user_id']);
        $transaction_stmt->bindParam(':reservation_id', $reservation['id']);
        $transaction_stmt->bindParam(':points', $points_earned);
        $description = "Points gagnés pour l'achat de billets - Réservation #" . $reservation['id'];
        $transaction_stmt->bindParam(':description', $description);
        $transaction_stmt->execute();
        
        http_response_code(200);
        echo json_encode(array(
            "message" => "Points ajoutés avec succès.",
            "points_earned" => $points_earned
        ));
        
    } elseif ($data->action == 'use_points' && !empty($data->points_to_use)) {
        // Utiliser des points pour une réduction
        $user_query = "SELECT points_fidelite FROM users WHERE id = :user_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $user_data['user_id']);
        $user_stmt->execute();
        $current_points = $user_stmt->fetchColumn();
        
        if ($current_points < $data->points_to_use) {
            http_response_code(400);
            echo json_encode(array("message" => "Points insuffisants."));
            exit;
        }
        
        // Calculer la réduction (1 point = 1 DH de réduction)
        $discount = $data->points_to_use;
        
        http_response_code(200);
        echo json_encode(array(
            "discount" => $discount,
            "points_used" => $data->points_to_use
        ));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $auth = new AuthMiddleware();
    $user_data = $auth->authenticateRequest();
    
    if (!$user_data) {
        http_response_code(401);
        echo json_encode(array("message" => "Token invalide."));
        exit;
    }
    
    // Récupérer l'historique des points
    $query = "SELECT lt.*, r.prix_total, 
                ed.nom as equipe_domicile, 
                ee.nom as equipe_exterieur
              FROM loyalty_transactions lt
              LEFT JOIN reservations r ON lt.reservation_id = r.id
              LEFT JOIN matchs m ON r.match_id = m.id
              LEFT JOIN equipes ed ON m.equipe_domicile_id = ed.id
              LEFT JOIN equipes ee ON m.equipe_exterieur_id = ee.id
              WHERE lt.user_id = :user_id
              ORDER BY lt.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_data['user_id']);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer le total des points
    $points_query = "SELECT points_fidelite FROM users WHERE id = :user_id";
    $points_stmt = $db->prepare($points_query);
    $points_stmt->bindParam(':user_id', $user_data['user_id']);
    $points_stmt->execute();
    $total_points = $points_stmt->fetchColumn();
    
    http_response_code(200);
    echo json_encode(array(
        "total_points" => $total_points,
        "transactions" => $transactions
    ));
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>