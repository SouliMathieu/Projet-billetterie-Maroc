<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Admin-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Vérification admin simple (à améliorer avec un vrai système d'auth admin)
function isAdmin() {
    $headers = getallheaders();
    $admin_token = $headers['Admin-Token'] ?? '';
    return $admin_token === 'admin123'; // Token simple pour la démo
}

if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(array("message" => "Accès non autorisé."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $user_id = $_GET['id'] ?? null;
    
    if ($user_id) {
        // Récupérer les détails d'un utilisateur spécifique avec ses réservations
        $query = "SELECT 
                    u.id,
                    u.nom,
                    u.email,
                    u.telephone,
                    u.points_fidelite,
                    u.created_at
                  FROM users u
                  WHERE u.id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Récupérer les réservations de l'utilisateur
            $reservations_query = "SELECT 
                                    r.id,
                                    r.prix_total,
                                    r.statut,
                                    r.created_at,
                                    CONCAT(ed.nom, ' vs ', ee.nom) as match_info,
                                    m.date_match
                                  FROM reservations r
                                  JOIN matchs m ON r.match_id = m.id
                                  JOIN equipes ed ON m.equipe_domicile_id = ed.id
                                  JOIN equipes ee ON m.equipe_exterieur_id = ee.id
                                  WHERE r.user_id = :user_id
                                  ORDER BY r.created_at DESC";
            
            $res_stmt = $db->prepare($reservations_query);
            $res_stmt->bindParam(':user_id', $user_id);
            $res_stmt->execute();
            $user['reservations'] = $res_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Utilisateur non trouvé."));
        }
    } else {
        // Récupérer tous les utilisateurs avec statistiques
        $query = "SELECT 
                    u.id,
                    u.nom,
                    u.email,
                    u.telephone,
                    u.points_fidelite,
                    u.created_at,
                    COUNT(r.id) as total_reservations,
                    COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.prix_total ELSE 0 END), 0) as total_depense
                  FROM users u
                  LEFT JOIN reservations r ON u.id = r.user_id
                  GROUP BY u.id
                  ORDER BY u.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($users);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Mettre à jour les points de fidélité d'un utilisateur
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id) && isset($data->points_fidelite)) {
        $query = "UPDATE users SET 
                  points_fidelite = :points_fidelite
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':points_fidelite', $data->points_fidelite);
        $stmt->bindParam(':id', $data->id);
        
        if ($stmt->execute()) {
            // Enregistrer la transaction de fidélité
            $transaction_query = "INSERT INTO loyalty_transactions (user_id, points, type, description, points_balance_after) 
                                 VALUES (:user_id, :points, 'bonus', 'Ajustement admin', :points_balance)";
            $transaction_stmt = $db->prepare($transaction_query);
            $transaction_stmt->bindParam(':user_id', $data->id);
            $transaction_stmt->bindParam(':points', $data->points_fidelite);
            $transaction_stmt->bindParam(':points_balance', $data->points_fidelite);
            $transaction_stmt->execute();
            
            http_response_code(200);
            echo json_encode(array("message" => "Points mis à jour avec succès."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la mise à jour."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Données manquantes."));
    }
    
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>