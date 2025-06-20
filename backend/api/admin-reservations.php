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
    // Récupérer toutes les réservations avec détails
    $query = "SELECT 
                r.id,
                r.nom_client,
                r.email_client,
                r.telephone_client,
                r.nombre_billets,
                r.categorie,
                r.prix_total,
                r.statut,
                r.created_at,
                r.confirmed_at,
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
              ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode($reservations);
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Mettre à jour une réservation (confirmer/annuler)
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id) && !empty($data->action)) {
        $new_status = '';
        $confirmed_at = null;
        
        switch ($data->action) {
            case 'confirm':
                $new_status = 'confirme';
                $confirmed_at = date('Y-m-d H:i:s');
                break;
            case 'cancel':
                $new_status = 'annule';
                break;
            default:
                http_response_code(400);
                echo json_encode(array("message" => "Action non valide."));
                exit;
        }
        
        $query = "UPDATE reservations SET 
                  statut = :statut,
                  confirmed_at = :confirmed_at
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':statut', $new_status);
        $stmt->bindParam(':confirmed_at', $confirmed_at);
        $stmt->bindParam(':id', $data->id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Réservation mise à jour avec succès."));
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