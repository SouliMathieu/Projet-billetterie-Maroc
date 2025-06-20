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
    // Récupérer toutes les équipes
    $query = "SELECT 
                id,
                nom,
                ville,
                logo_url,
                couleur_principale,
                couleur_secondaire,
                fondation_annee,
                description,
                created_at
              FROM equipes
              ORDER BY nom ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $equipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode($equipes);
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Créer une nouvelle équipe
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->nom) && !empty($data->ville)) {
        $query = "INSERT INTO equipes 
                  (nom, ville, logo_url, couleur_principale, couleur_secondaire, fondation_annee, description) 
                  VALUES (:nom, :ville, :logo_url, :couleur_principale, :couleur_secondaire, :fondation_annee, :description)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nom', $data->nom);
        $stmt->bindParam(':ville', $data->ville);
        $stmt->bindParam(':logo_url', $data->logo_url ?? null);
        $stmt->bindParam(':couleur_principale', $data->couleur_principale ?? '#000000');
        $stmt->bindParam(':couleur_secondaire', $data->couleur_secondaire ?? '#FFFFFF');
        $stmt->bindParam(':fondation_annee', $data->fondation_annee ?? null);
        $stmt->bindParam(':description', $data->description ?? null);
        
        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(array("message" => "Équipe créée avec succès.", "id" => $db->lastInsertId()));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la création de l'équipe."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Nom et ville requis."));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Mettre à jour une équipe
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id)) {
        $query = "UPDATE equipes SET 
                  nom = :nom,
                  ville = :ville,
                  logo_url = :logo_url,
                  couleur_principale = :couleur_principale,
                  couleur_secondaire = :couleur_secondaire,
                  fondation_annee = :fondation_annee,
                  description = :description
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        $stmt->bindParam(':nom', $data->nom);
        $stmt->bindParam(':ville', $data->ville);
        $stmt->bindParam(':logo_url', $data->logo_url);
        $stmt->bindParam(':couleur_principale', $data->couleur_principale);
        $stmt->bindParam(':couleur_secondaire', $data->couleur_secondaire);
        $stmt->bindParam(':fondation_annee', $data->fondation_annee);
        $stmt->bindParam(':description', $data->description);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Équipe mise à jour avec succès."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la mise à jour."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID de l'équipe requis."));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Supprimer une équipe
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id)) {
        // Vérifier s'il y a des matchs associés
        $check_query = "SELECT COUNT(*) FROM matchs WHERE equipe_domicile_id = :equipe_id OR equipe_exterieur_id = :equipe_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':equipe_id', $data->id);
        $check_stmt->execute();
        $matches_count = $check_stmt->fetchColumn();
        
        if ($matches_count > 0) {
            http_response_code(400);
            echo json_encode(array("message" => "Impossible de supprimer une équipe avec des matchs associés."));
            exit;
        }
        
        $query = "DELETE FROM equipes WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Équipe supprimée avec succès."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Erreur lors de la suppression."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID de l'équipe requis."));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>