<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../api/authMiddleware.php';
require_once 'DynamicPricing.php';

$database = new Database();
$db = $database->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Authentification
        $auth = new AuthMiddleware();
        $user_data = $auth->authenticateRequest();
        
        if (!$user_data) {
            throw new Exception("Token invalide ou expiré", 401);
        }

        // Récupération des données
        $input = json_decode(file_get_contents("php://input"), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Format JSON invalide", 400);
        }

        // Validation des données
        $required_fields = ['match_id', 'nombre_billets', 'categorie', 'nom_client', 'email_client', 'telephone_client'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                throw new Exception("Le champ $field est requis", 400);
            }
        }

        // Vérification du match
        $match_stmt = $db->prepare("
            SELECT m.*, s.capacite_vip, s.capacite_normale, s.capacite_tribune 
            FROM matchs m 
            JOIN stades s ON m.stade_id = s.id 
            WHERE m.id = :match_id
        ");
        $match_stmt->execute([':match_id' => $input['match_id']]);
        $match = $match_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            throw new Exception("Match non trouvé", 404);
        }

        // Vérification disponibilité
        $sold_stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN categorie = 'VIP' THEN nombre_billets ELSE 0 END) as vip_vendus,
                SUM(CASE WHEN categorie = 'Normale' THEN nombre_billets ELSE 0 END) as normale_vendus,
                SUM(CASE WHEN categorie = 'Tribune' THEN nombre_billets ELSE 0 END) as tribune_vendus
            FROM reservations 
            WHERE match_id = :match_id AND statut IN ('confirme', 'en_attente')
        ");
        $sold_stmt->execute([':match_id' => $input['match_id']]);
        $vendus = $sold_stmt->fetch(PDO::FETCH_ASSOC);

        // Calcul places disponibles
        $categorie = strtolower($input['categorie']);
        $capacite = $match["capacite_$categorie"];
        $vendus = $vendus["{$categorie}_vendus"] ?? 0;
        $disponible = $capacite - $vendus;

        if ($disponible < $input['nombre_billets']) {
            throw new Exception("Plus que $disponible places disponibles en catégorie {$input['categorie']}", 400);
        }

        // Calcul prix dynamique
        $pricing = new DynamicPricing();
        $match_date = new DateTime($match['date_match']);
        $prix_base = $match["prix_base_$categorie"];
        $prix_unitaire = $pricing->calculatePrice($prix_base, $match_date, $match['coefficient_multiplicateur']);
        $prix_total = $prix_unitaire * $input['nombre_billets'];

        // Création réservation
        $db->beginTransaction();
        
        try {
            $reservation_stmt = $db->prepare("
                INSERT INTO reservations 
                (user_id, match_id, nom_client, email_client, telephone_client, 
                 nombre_billets, categorie, prix_unitaire, prix_total, statut) 
                VALUES 
                (:user_id, :match_id, :nom_client, :email_client, :telephone_client, 
                 :nombre_billets, :categorie, :prix_unitaire, :prix_total, 'en_attente')
            ");
            
            $reservation_data = [
                ':user_id' => $user_data['user_id'],
                ':match_id' => $input['match_id'],
                ':nom_client' => $input['nom_client'],
                ':email_client' => $input['email_client'],
                ':telephone_client' => $input['telephone_client'],
                ':nombre_billets' => $input['nombre_billets'],
                ':categorie' => $input['categorie'],
                ':prix_unitaire' => $prix_unitaire,
                ':prix_total' => $prix_total
            ];
            
            if (!$reservation_stmt->execute($reservation_data)) {
                throw new Exception("Erreur lors de la création de la réservation", 500);
            }
            
            $reservation_id = $db->lastInsertId();
            $db->commit();
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'reservation_id' => $reservation_id,
                'prix_total' => $prix_total,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // Récupération des réservations utilisateur
        $auth = new AuthMiddleware();
        $user_data = $auth->authenticateRequest();
        
        if (!$user_data) {
            throw new Exception("Token invalide ou expiré", 401);
        }
        
        $query = "
            SELECT r.*, 
                ed.nom as equipe_domicile, 
                ee.nom as equipe_exterieur,
                m.date_match,
                s.nom as stade_nom,
                s.ville as stade_ville
            FROM reservations r
            JOIN matchs m ON r.match_id = m.id
            JOIN equipes ed ON m.equipe_domicile_id = ed.id
            JOIN equipes ee ON m.equipe_exterieur_id = ee.id
            JOIN stades s ON m.stade_id = s.id
            WHERE r.user_id = :user_id
            ORDER BY r.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $user_data['user_id']]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($reservations);
        
    } else {
        throw new Exception("Méthode non autorisée", 405);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>