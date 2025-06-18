<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Content-Type: application/json; charset=UTF-8");

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


include_once '../config/database.php';
include_once 'DynamicPricing.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT 
                m.id, 
                m.date_match, 
                m.prix_base_vip, 
                m.prix_base_normale, 
                m.prix_base_tribune,
                m.coefficient_multiplicateur,
                m.statut,
                ed.nom as equipe_domicile, 
                ed.ville as ville_domicile,
                ed.logo_url as logo_domicile,
                ee.nom as equipe_exterieur,
                ee.logo_url as logo_exterieur,
                s.nom as stade_nom,
                s.ville as stade_ville,
                s.capacite_vip,
                s.capacite_normale,
                s.capacite_tribune,
                s.adresse as stade_adresse
              FROM matchs m
              JOIN equipes ed ON m.equipe_domicile_id = ed.id
              JOIN equipes ee ON m.equipe_exterieur_id = ee.id
              JOIN stades s ON m.stade_id = s.id
              WHERE m.date_match > NOW() AND m.statut = 'programme'
              ORDER BY m.date_match ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($matches)) {
    http_response_code(200);
    echo json_encode(array("message" => "Aucun match programmé", "data" => []));
    exit();
}
    $pricing = new DynamicPricing();
    
    // Calculer les prix dynamiques et les disponibilités pour chaque match
    foreach ($matches as &$match) {
        $match_date = new DateTime($match['date_match']);
        $now = new DateTime();
        
        // Calculer les prix dynamiques
        $match['prix_dynamique_vip'] = $pricing->calculatePrice(
            $match['prix_base_vip'], 
            $match_date, 
            $match['coefficient_multiplicateur']
        );
        $match['prix_dynamique_normale'] = $pricing->calculatePrice(
            $match['prix_base_normale'], 
            $match_date, 
            $match['coefficient_multiplicateur']
        );
        $match['prix_dynamique_tribune'] = $pricing->calculatePrice(
            $match['prix_base_tribune'], 
            $match_date, 
            $match['coefficient_multiplicateur']
        );
        
        // Calculer les places disponibles
        $reservations_query = "SELECT 
                                SUM(CASE WHEN categorie = 'VIP' THEN nombre_billets ELSE 0 END) as vip_vendus,
                                SUM(CASE WHEN categorie = 'Normale' THEN nombre_billets ELSE 0 END) as normale_vendus,
                                SUM(CASE WHEN categorie = 'Tribune' THEN nombre_billets ELSE 0 END) as tribune_vendus
                               FROM reservations 
                               WHERE match_id = :match_id AND statut = 'confirme'";
        
        $res_stmt = $db->prepare($reservations_query);
        $res_stmt->bindParam(':match_id', $match['id']);
        $res_stmt->execute();
        $vendus = $res_stmt->fetch(PDO::FETCH_ASSOC);
        
        $match['places_disponibles'] = array(
            'vip' => $match['capacite_vip'] - ($vendus['vip_vendus'] ?? 0),
            'normale' => $match['capacite_normale'] - ($vendus['normale_vendus'] ?? 0),
            'tribune' => $match['capacite_tribune'] - ($vendus['tribune_vendus'] ?? 0)
        );
        
        // Formatage des dates
        $match['date_formatted'] = $match_date->format('d/m/Y');
        $match['heure_formatted'] = $match_date->format('H:i');
        $match['jours_restants'] = $now->diff($match_date)->days;
    }
    
    http_response_code(200);
    echo json_encode($matches);
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>