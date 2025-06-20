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
    // Récupérer tous les stades
    $query = "SELECT 
                id,
                nom,
                ville,
                capacite_totale,
                capacite_vip,
                capacite_normale,
                capacite_tribune,
                adresse,
                latitude,
                longitude,
                annee_construction,
                description,
                created_at
              FROM stades
              ORDER BY nom ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode($stades);
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Créer un nouveau stade
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->nom) && !empty($data->ville) && !empty($data->capacite_totale)) {
        $query = "INSERT INTO stades 
                  (nom, ville, capacite_totale, capacite_vip, capacite_normale, capacite_tribune, adresse, latitude, longitude, annee_construction, description) 
                  VALUES (:nom, :ville, :capacite_totale, :capacite_vip, :capacite_normale, :capacite_tribune, :adresse, :latitude, :longitude, :annee_construction, :description)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nom', $data->nom);
        $stmt->bindParam(':ville', $data->ville);
        $stmt->bindParam(':capacite_totale', $data->capacite_totale);
        $stmt->bindParam(':capacite_vip', $data->capacite_vip ?? 0);
        $stmt->bindParam(':capacite_normale', $data->capacite_normale ?? 0);
        $stmt->bindParam(':capacite_tribune', $data->capacite_tribune ?? 0);
        $stmt->bindParam(':adresse', $data->adresse ?? null);
        $stmt->bindParam(':latitude', $data->latitude ?? null);
        $stmt->bindParam(':longitude', $data->longitude ?? null);
        $stmt->bindParam(':annee_construction', $data->annee_construction ?? null);
        $stmt->bindParam(':description', $data->description ?? null);
        
        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(array("message" => "Stade créé avec succès.", "id" => $db->lastInsertId()));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la création du stade."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Nom, ville et capacité totale requis."));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Mettre à jour un stade
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id)) {
        $query = "UPDATE stades SET 
                  nom = :nom,
                  ville = :ville,
                  capacite_totale = :capacite_totale,
                  capacite_vip = :capacite_vip,
                  capacite_normale = :capacite_normale,
                  capacite_tribune = :capacite_tribune,
                  adresse = :adresse,
                  latitude = :latitude,
                  longitude = :longitude,
                  annee_construction = :annee_construction,
                  description = :description
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        $stmt->bindParam(':nom', $data->nom);
        $stmt->bindParam(':ville', $data->ville);
        $stmt->bindParam(':capacite_totale', $data->capacite_totale);
        $stmt->bindParam(':capacite_vip', $data->capacite_vip);
        $stmt->bindParam(':capacite_normale', $data->capacite_normale);
        $stmt->bindParam(':capacite_tribune', $data->capacite_tribune);
        $stmt->bindParam(':adresse', $data->adresse);
        $stmt->bindParam(':latitude', $data->latitude);
        $stmt->bindParam(':longitude', $data->longitude);
        $stmt->bindParam(':annee_construction', $data->annee_construction);
        $stmt->bindParam(':description', $data->description);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Stade mis à jour avec succès."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la mise à jour."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID du stade requis."));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Supprimer un stade
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id)) {
        // Vérifier s'il y a des matchs associés
        $check_query = "SELECT COUNT(*) FROM matchs WHERE stade_id = :stade_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':stade_id', $data->id);
        $check_stmt->execute();
        $matches_count = $check_stmt->fetchColumn();
        
        if ($matches_count > 0) {
            http_response_code(400);
            echo json_encode(array("message" => "Impossible de supprimer un stade avec des matchs associés."));
            exit;
        }
        
        $query = "DELETE FROM stades WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Stade supprimé avec succès."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la suppression."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID du stade requis."));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>