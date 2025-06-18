<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Ajout des headers de sécurité recommandés
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';
include_once 'authMiddleware.php';

$database = new Database();
$db = $database->getConnection();

// Configuration PayPal Sandbox
$client_id = 'AZFUIlhYG3ambcEbdDjyD0SBZ1-eVe6j1zG2CG-Zw7p8LCQGYuSX36MUc0iHMNBzkd7pI6JFr2tzJdGZ';
$client_secret = 'EKtMCWD5Il5arYOc9K654QvSCZ5iim_MrDKetnzUS1E2hKuk7Ny2DZ3FtH0dw88ywaZ9iiJCMcqNb-3U';
$base_url = 'https://api-m.sandbox.paypal.com';

function getPayPalAccessToken($client_id, $client_secret, $base_url) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $base_url . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret),
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($http_code != 200 || $error) {
        error_log("Erreur PayPal API: Code $http_code - $error");
        return null;
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auth = new AuthMiddleware();
    $user_data = $auth->authenticateRequest();
    
    if (!$user_data) {
        http_response_code(401);
        echo json_encode(["message" => "Token invalide."]);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        if ($data->action == 'create_order' && !empty($data->reservation_id)) {
            // Récupération réservation avec plus de détails
            $stmt = $db->prepare("SELECT r.*, 
                                ed.nom as equipe_domicile, 
                                ee.nom as equipe_exterieur,
                                m.date_match,
                                s.nom as stade_nom
                              FROM reservations r
                              JOIN matchs m ON r.match_id = m.id
                              JOIN equipes ed ON m.equipe_domicile_id = ed.id
                              JOIN equipes ee ON m.equipe_exterieur_id = ee.id
                              JOIN stades s ON m.stade_id = s.id
                              WHERE r.id = ? AND r.user_id = ? AND r.statut = 'en_attente'");
            $stmt->execute([$data->reservation_id, $user_data['user_id']]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reservation) {
                throw new Exception("Réservation non trouvée ou déjà traitée", 404);
            }

            // Création commande PayPal
            $access_token = getPayPalAccessToken($client_id, $client_secret, $base_url);
            if (!$access_token) {
                throw new Exception("Erreur d'authentification PayPal", 500);
            }
            
            // Conversion MAD en USD (taux approximatif)
            $amount_usd = $reservation['prix_total'] / 10;
            
            $order_data = [
                "intent" => "CAPTURE",
                "purchase_units" => [[
                    "reference_id" => "RES_" . $reservation['id'],
                    "amount" => [
                        "currency_code" => "USD",
                        "value" => number_format($amount_usd, 2),
                        "breakdown" => [
                            "item_total" => [
                                "currency_code" => "USD",
                                "value" => number_format($amount_usd, 2)
                            ]
                        ]
                    ],
                    "description" => "Billets: " . $reservation['equipe_domicile'] . " vs " . $reservation['equipe_exterieur'],
                    "items" => [
                        [
                            "name" => "Billet " . $reservation['categorie'] . " - " . $reservation['equipe_domicile'] . " vs " . $reservation['equipe_exterieur'],
                            "quantity" => $reservation['nombre_billets'],
                            "unit_amount" => [
                                "currency_code" => "USD",
                                "value" => number_format($amount_usd / $reservation['nombre_billets'], 2)
                            ]
                        ]
                    ]
                ]],
                "application_context" => [
                    "return_url" => "http://localhost:5173/payment/" . $reservation['id'] . "?success=true",
                    "cancel_url" => "http://localhost:5173/payment/" . $reservation['id'] . "?cancel=true",
                    "brand_name" => "Billetterie Football Maroc",
                    "locale" => "fr-FR",
                    "user_action" => "PAY_NOW"
                ]
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $base_url . '/v2/checkout/orders',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($order_data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token,
                    'PayPal-Request-Id: ' . uniqid()
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
            curl_close($curl);
            
            if ($http_code != 201) {
                error_log("Erreur création commande PayPal. Code: $http_code - Erreur: $curl_error - Réponse: $response");
                throw new Exception("Erreur lors de la création de la commande PayPal", 500);
            }
            
            $order = json_decode($response, true);
            
            // Mise à jour réservation avec l'ID de commande PayPal
            $update_stmt = $db->prepare("UPDATE reservations SET paypal_order_id = ? WHERE id = ?");
            $update_stmt->execute([$order['id'], $reservation['id']]);
            
            // Trouver l'URL d'approbation
            $approve_url = null;
            foreach ($order['links'] as $link) {
                if ($link['rel'] == 'approve') {
                    $approve_url = $link['href'];
                    break;
                }
            }
            
            if (!$approve_url) {
                throw new Exception("URL d'approbation PayPal non trouvée", 500);
            }
            
            echo json_encode([
                "status" => "success",
                "order_id" => $order['id'],
                "approval_url" => $approve_url,
                "reservation_id" => $reservation['id']
            ]);

        } elseif ($data->action == 'capture_order' && !empty($data->order_id)) {
            // Capture paiement
            $access_token = getPayPalAccessToken($client_id, $client_secret, $base_url);
            if (!$access_token) {
                throw new Exception("Erreur d'authentification PayPal", 500);
            }
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $base_url . '/v2/checkout/orders/' . $data->order_id . '/capture',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
            curl_close($curl);
            
            if ($http_code != 201) {
                error_log("Erreur capture paiement PayPal. Code: $http_code - Erreur: $curl_error - Réponse: $response");
                throw new Exception("Erreur lors de la capture du paiement PayPal", 500);
            }
            
            $capture = json_decode($response, true);
            
            if ($capture['status'] != 'COMPLETED') {
                throw new Exception("Paiement non complété", 400);
            }
            
            // Mise à jour complète réservation
            $db->beginTransaction();
            
            try {
                // 1. Mettre à jour la réservation
                $update_stmt = $db->prepare("UPDATE reservations SET 
                    statut = 'confirme',
                    paypal_payment_id = ?,
                    transaction_id = ?,
                    confirmed_at = NOW(),
                    expires_at = NULL
                    WHERE paypal_order_id = ?");
                
                $update_stmt->execute([
                    $capture['purchase_units'][0]['payments']['captures'][0]['id'],
                    $data->order_id,
                    $data->order_id
                ]);
                
                // 2. Récupérer l'ID de la réservation mise à jour
                $stmt = $db->prepare("SELECT id FROM reservations WHERE paypal_order_id = ?");
                $stmt->execute([$data->order_id]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$reservation) {
                    throw new Exception("Réservation non trouvée après mise à jour", 404);
                }
                
                $db->commit();
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Paiement confirmé avec succès",
                    "reservation_id" => $reservation['id'],
                    "payment_id" => $capture['purchase_units'][0]['payments']['captures'][0]['id'],
                    "order_id" => $data->order_id
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } else {
            throw new Exception("Requête invalide", 400);
        }
    } catch (Exception $e) {
        error_log("Erreur PayPal: " . $e->getMessage());
        http_response_code($e->getCode() ?: 500);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage(),
            "details" => $e->getCode() >= 500 ? null : json_decode($response ?? '{}', true)
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(["message" => "Méthode non autorisée"]);
}
?>