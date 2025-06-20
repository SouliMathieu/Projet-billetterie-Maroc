<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
    try {
        $analytics = array();
        
        // Ventes du mois en cours
        $monthly_sales_query = "SELECT COALESCE(SUM(prix_total), 0) as total
                               FROM reservations 
                               WHERE statut = 'confirme' 
                               AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                               AND YEAR(created_at) = YEAR(CURRENT_DATE())";
        $stmt = $db->prepare($monthly_sales_query);
        $stmt->execute();
        $analytics['monthly_sales'] = $stmt->fetchColumn();
        
        // Croissance des ventes (comparaison avec le mois précédent)
        $previous_month_query = "SELECT COALESCE(SUM(prix_total), 0) as total
                                FROM reservations 
                                WHERE statut = 'confirme' 
                                AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
                                AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
        $stmt = $db->prepare($previous_month_query);
        $stmt->execute();
        $previous_month_sales = $stmt->fetchColumn();
        
        if ($previous_month_sales > 0) {
            $analytics['sales_growth'] = round((($analytics['monthly_sales'] - $previous_month_sales) / $previous_month_sales) * 100, 1);
        } else {
            $analytics['sales_growth'] = 0;
        }
        
        // Taux de remplissage moyen
        $occupancy_query = "SELECT 
                              AVG((billets_vendus / capacite_totale) * 100) as avg_occupancy
                            FROM (
                              SELECT 
                                m.id,
                                s.capacite_totale,
                                COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.nombre_billets ELSE 0 END), 0) as billets_vendus
                              FROM matchs m
                              JOIN stades s ON m.stade_id = s.id
                              LEFT JOIN reservations r ON m.id = r.match_id
                              WHERE m.date_match < NOW()
                              GROUP BY m.id, s.capacite_totale
                            ) as match_stats";
        $stmt = $db->prepare($occupancy_query);
        $stmt->execute();
        $analytics['avg_occupancy'] = round($stmt->fetchColumn() ?: 0, 1);
        
        // Match le plus populaire
        $popular_match_query = "SELECT 
                                  CONCAT(ed.nom, ' vs ', ee.nom) as teams,
                                  DATE_FORMAT(m.date_match, '%d/%m/%Y') as date,
                                  COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.nombre_billets ELSE 0 END), 0) as tickets
                                FROM matchs m
                                JOIN equipes ed ON m.equipe_domicile_id = ed.id
                                JOIN equipes ee ON m.equipe_exterieur_id = ee.id
                                LEFT JOIN reservations r ON m.id = r.match_id
                                GROUP BY m.id
                                ORDER BY tickets DESC
                                LIMIT 1";
        $stmt = $db->prepare($popular_match_query);
        $stmt->execute();
        $analytics['popular_match'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Évolution des revenus (6 derniers mois)
        $revenue_chart = array();
        for ($i = 5; $i >= 0; $i--) {
            $month_query = "SELECT 
                              DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL $i MONTH), '%b') as month,
                              COALESCE(SUM(prix_total), 0) as revenue
                            FROM reservations 
                            WHERE statut = 'confirme' 
                            AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL $i MONTH))
                            AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL $i MONTH))";
            $stmt = $db->prepare($month_query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $revenue_chart[] = array(
                'month' => $result['month'],
                'revenue' => floatval($result['revenue'])
            );
        }
        $analytics['revenue_chart'] = $revenue_chart;
        
        // Répartition par catégorie
        $category_query = "SELECT 
                            categorie,
                            COUNT(*) as count,
                            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM reservations WHERE statut = 'confirme')), 1) as percentage
                          FROM reservations 
                          WHERE statut = 'confirme'
                          GROUP BY categorie";
        $stmt = $db->prepare($category_query);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $category_distribution = array();
        foreach ($categories as $cat) {
            $color = '';
            switch ($cat['categorie']) {
                case 'VIP': $color = 'bg-yellow-500'; break;
                case 'Normale': $color = 'bg-blue-500'; break;
                case 'Tribune': $color = 'bg-green-500'; break;
            }
            $category_distribution[] = array(
                'name' => $cat['categorie'],
                'percentage' => floatval($cat['percentage']),
                'color' => $color
            );
        }
        $analytics['category_distribution'] = $category_distribution;
        
        // Top 5 équipes populaires
        $popular_teams_query = "SELECT 
                                  e.nom as name,
                                  COUNT(r.id) as tickets
                                FROM equipes e
                                JOIN matchs m ON (e.id = m.equipe_domicile_id OR e.id = m.equipe_exterieur_id)
                                JOIN reservations r ON m.id = r.match_id
                                WHERE r.statut = 'confirme'
                                GROUP BY e.id, e.nom
                                ORDER BY tickets DESC
                                LIMIT 5";
        $stmt = $db->prepare($popular_teams_query);
        $stmt->execute();
        $analytics['popular_teams'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($analytics);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array(
            "message" => "Erreur lors du calcul des analytics.",
            "error" => $e->getMessage()
        ));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Méthode non autorisée."));
}
?>