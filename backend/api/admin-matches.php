<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
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
    // Récupérer tous les matchs avec statistiques
    $query = "SELECT 
                m.id, 
                m.date_match, 
                m.prix_base_vip, 
                m.prix_base_normale, 
                m.prix_base_tribune,
                m.coefficient_multiplicateur,
                m.statut,
                ed.nom as equipe_domicile, 
                ee.nom as equipe_exterieur,
                s.nom as stade_nom,
                s.ville as stade_ville,
                s.capacite_vip,
                s.capacite_normale,
                s.capacite_tribune,
                COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.prix_total ELSE 0 END), 0) as revenus_total,
                COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.nombre_billets ELSE 0 END), 0) as billets_vendus
              FROM matchs m
              JOIN equipes ed ON m.equipe_domicile_id = ed.id
              JOIN equipes ee ON m.equipe_exterieur_id = ee.id
              JOIN stades s ON m.stade_id = s.id
              LEFT JOIN reservations r ON m.id = r.match_id
              GROUP BY m.id
              ORDER BY m.date_match ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode($matches);
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Créer un nouveau match
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->equipe_domicile_id) && !empty($data->equipe_exterieur_id) && 
        !empty($data->stade_id) && !empty($data->date_match)) {
        
        $query = "INSERT INTO matchs 
                  (equipe_domicile_id, equipe_exterieur_id, stade_id, date_match, 
                   prix_base_vip, prix_base_normale, prix_base_tribune, coefficient_multiplicateur) 
                  VALUES (:equipe_domicile_id, :equipe_exterieur_id, :stade_id, :date_match,
                          :prix_base_vip, :prix_base_normale, :prix_base_tribune, :coefficient_multiplicateur)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':equipe_domicile_id', $data->equipe_domicile_id);
        $stmt->bindParam(':equipe_exterieur_id', $data->equipe_exterieur_id);
        $stmt->bindParam(':stade_id', $data->stade_id);
        $stmt->bindParam(':date_match', $data->date_match);
        $stmt->bindParam(':prix_base_vip', $data->prix_base_vip ?? 500);
        $stmt->bindParam(':prix_base_normale', $data->prix_base_normale ?? 200);
        $stmt->bindParam(':prix_base_tribune', $data->prix_base_tribune ?? 100);
        $stmt->bindParam(':coefficient_multiplicateur', $data->coefficient_multiplicateur ?? 1.0);
        
        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(array("message" => "Match créé avec succès.", "id" => $db->lastInsertId()));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la création du match."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Données incomplètes."));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Mettre à jour un match
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id)) {
        $query = "UPDATE matchs SET 
                  equipe_domicile_id = :equipe_domicile_id,
                  equipe_exterieur_id = :equipe_exterieur_id,
                  stade_id = :stade_id,
                  date_match = :date_match,
                  prix_base_vip = :prix_base_vip,
                  prix_base_normale = :prix_base_normale,
                  prix_base_tribune = :prix_base_tribune,
                  coefficient_multiplicateur = :coefficient_multiplicateur,
                  statut = :statut
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        $stmt->bindParam(':equipe_domicile_id', $data->equipe_domicile_id);
        $stmt->bindParam(':equipe_exterieur_id', $data->equipe_exterieur_id);
        $stmt->bindParam(':stade_id', $data->stade_id);
        $stmt->bindParam(':date_match', $data->date_match);
        $stmt->bindParam(':prix_base_vip', $data->prix_base_vip);
        $stmt->bindParam(':prix_base_normale', $data->prix_base_normale);
        $stmt->bindParam(':prix_base_tribune', $data->prix_base_tribune);
        $stmt->bindParam(':coefficient_multiplicateur', $data->coefficient_multiplicateur);
        $stmt->bindParam(':statut', $data->statut);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Match mis à jour avec succès."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la mise à jour."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID du match requis."));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Supprimer un match
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id)) {
        // Vérifier s'il y a des réservations
        $check_query = "SELECT COUNT(*) FROM reservations WHERE match_id = :match_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':match_id', $data->id);
        $check_stmt->execute();
        $reservations_count = $check_stmt->fetchColumn();
        
        if ($reservations_count > 0) {
            http_response_code(400);
            echo json_encode(array("message" => "Impossible de supprimer un match avec des réservations."));
            exit;
        }
        
        $query = "DELETE FROM matchs WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Match supprimé avec succès."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la suppression."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID du match requis."));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>