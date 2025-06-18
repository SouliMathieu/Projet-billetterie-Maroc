<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Récupérer le nom de la ville depuis les paramètres GET
    $ville = $_GET['ville'] ?? '';
    
    if (empty($ville)) {
        http_response_code(400);
        echo json_encode(array("message" => "Nom de ville requis."));
        exit;
    }
    
    try {
        $query = "SELECT 
                    ville,
                    region,
                    population,
                    transport_info,
                    activites_touristiques,
                    restaurants,
                    hotels,
                    liens_utiles,
                    climat_description,
                    langues_parlees,
                    monnaie,
                    fuseau_horaire
                  FROM ville_infos 
                  WHERE ville = :ville";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':ville', $ville);
        $stmt->execute();
        
        $ville_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ville_info) {
            http_response_code(200);
            echo json_encode($ville_info);
        } else {
            // Si la ville n'est pas trouvée, retourner des informations par défaut
            http_response_code(200);
            echo json_encode(array(
                "ville" => $ville,
                "region" => "Maroc",
                "transport_info" => "Transport local disponible, taxi et bus urbain",
                "activites_touristiques" => "Découvrez les attractions locales de " . $ville,
                "restaurants" => "Restaurants locaux et cuisine marocaine traditionnelle",
                "hotels" => "Hébergements disponibles en centre-ville",
                "liens_utiles" => "Office de tourisme local",
                "climat_description" => "Climat méditerranéen/continental selon la région",
                "langues_parlees" => "Arabe, Français",
                "monnaie" => "MAD",
                "fuseau_horaire" => "UTC+1"
            ));
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array(
            "message" => "Erreur lors de la récupération des informations de ville.",
            "error" => $e->getMessage()
        ));
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Optionnel: Permettre l'ajout/mise à jour d'informations de ville (pour les admins)
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->ville)) {
        try {
            $query = "INSERT INTO ville_infos 
                      (ville, region, population, transport_info, activites_touristiques, 
                       restaurants, hotels, liens_utiles, climat_description, langues_parlees, monnaie, fuseau_horaire) 
                      VALUES (:ville, :region, :population, :transport_info, :activites_touristiques, 
                              :restaurants, :hotels, :liens_utiles, :climat_description, :langues_parlees, :monnaie, :fuseau_horaire)
                      ON DUPLICATE KEY UPDATE
                      region = VALUES(region),
                      population = VALUES(population),
                      transport_info = VALUES(transport_info),
                      activites_touristiques = VALUES(activites_touristiques),
                      restaurants = VALUES(restaurants),
                      hotels = VALUES(hotels),
                      liens_utiles = VALUES(liens_utiles),
                      climat_description = VALUES(climat_description),
                      langues_parlees = VALUES(langues_parlees),
                      monnaie = VALUES(monnaie),
                      fuseau_horaire = VALUES(fuseau_horaire),
                      updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':ville', $data->ville);
            $stmt->bindParam(':region', $data->region ?? null);
            $stmt->bindParam(':population', $data->population ?? null);
            $stmt->bindParam(':transport_info', $data->transport_info ?? null);
            $stmt->bindParam(':activites_touristiques', $data->activites_touristiques ?? null);
            $stmt->bindParam(':restaurants', $data->restaurants ?? null);
            $stmt->bindParam(':hotels', $data->hotels ?? null);
            $stmt->bindParam(':liens_utiles', $data->liens_utiles ?? null);
            $stmt->bindParam(':climat_description', $data->climat_description ?? null);
            $stmt->bindParam(':langues_parlees', $data->langues_parlees ?? 'Arabe, Français');
            $stmt->bindParam(':monnaie', $data->monnaie ?? 'MAD');
            $stmt->bindParam(':fuseau_horaire', $data->fuseau_horaire ?? 'UTC+1');
            
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array("message" => "Informations de ville ajoutées/mises à jour avec succès."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Erreur lors de l'ajout des informations."));
            }
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(array(
                "message" => "Erreur lors de l'ajout des informations de ville.",
                "error" => $e->getMessage()
            ));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Nom de ville requis."));
    }
    
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>