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
    
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    // Vérifier si les données JSON sont valides
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["message" => "Données JSON invalides"]);
        exit;
    }
    
    try {
        if ($data->action == 'create_order' && !empty($data->reservation_id)) {
            // Récupération réservation avec plus de détails
            $stmt = $db->prepare("SELECT r.*, 
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
            $amount_usd = round($reservation['prix_total'] / 10, 2);
            $unit_amount = round($amount_usd / $reservation['nombre_billets'], 2);
            
            $order_data = [
                "intent" => "CAPTURE",
                "purchase_units" => [[
                    "reference_id" => "RES_" . $reservation['id'],
                    "amount" => [
                        "currency_code" => "USD",
                        "value" => sprintf("%.2f", $amount_usd),
                        "breakdown" => [
                            "item_total" => [
                                "currency_code" => "USD",
                                "value" => sprintf("%.2f", $amount_usd)
                            ]
                        ]
                    ],
                    "description" => "Billets: " . $reservation['equipe_domicile'] . " vs " . $reservation['equipe_exterieur'],
                    "items" => [
                        [
                            "name" => "Billet " . $reservation['categorie'] . " - " . $reservation['equipe_domicile'] . " vs " . $reservation['equipe_exterieur'],
                            "quantity" => (string)$reservation['nombre_billets'],
                            "unit_amount" => [
                                "currency_code" => "USD",
                                "value" => sprintf("%.2f", $unit_amount)
                            ]
                        ]
                    ]
                ]],
                "application_context" => [
                    "return_url" => "http://localhost:5173/payment/" . $reservation['id'] . "?success=true",
                    "cancel_url" => "http://localhost:5173/payment/" . $reservation['id'] . "?cancelled=true",
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
            
            // IMPORTANT : Mise à jour réservation avec l'ID de commande PayPal
            $update_stmt = $db->prepare("UPDATE reservations SET paypal_order_id = ? WHERE id = ?");
            $update_result = $update_stmt->execute([$order['id'], $reservation['id']]);
            
            if (!$update_result) {
                error_log("Erreur mise à jour paypal_order_id pour réservation " . $reservation['id']);
                throw new Exception("Erreur lors de la mise à jour de la réservation", 500);
            }
            
            // Log pour debug
            error_log("Commande PayPal créée : " . $order['id'] . " pour réservation " . $reservation['id']);
            
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
            // CORRECTION : Rechercher par token ET par order_id
            $order_id = $data->order_id;
            
            // D'abord essayer avec order_id direct
            $reservation_stmt = $db->prepare("SELECT * FROM reservations WHERE paypal_order_id = ? AND user_id = ?");
            $reservation_stmt->execute([$order_id, $user_data['user_id']]);
            $reservation = $reservation_stmt->fetch(PDO::FETCH_ASSOC);

            // Si pas trouvé, essayer de récupérer les détails de la commande PayPal
            if (!$reservation) {
                error_log("Réservation non trouvée avec order_id: $order_id pour user: " . $user_data['user_id']);
                
                // Récupérer les détails de la commande PayPal pour debug
                $access_token = getPayPalAccessToken($client_id, $client_secret, $base_url);
                if ($access_token) {
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => $base_url . '/v2/checkout/orders/' . $order_id,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $access_token
                        ]
                    ]);
                    
                    $order_details = curl_exec($curl);
                    curl_close($curl);
                    error_log("Détails commande PayPal: " . $order_details);
                }
                
                throw new Exception("Réservation non trouvée pour cette commande PayPal (Order ID: $order_id)", 404);
            }

            // Vérifier le statut de la réservation
            if ($reservation['statut'] === 'confirme') {
                echo json_encode([
                    "status" => "success",
                    "message" => "Paiement déjà confirmé",
                    "reservation_id" => $reservation['id'],
                    "already_processed" => true
                ]);
                exit;
            }

            // Capture paiement
            $access_token = getPayPalAccessToken($client_id, $client_secret, $base_url);
            if (!$access_token) {
                throw new Exception("Erreur d'authentification PayPal", 500);
            }
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $base_url . '/v2/checkout/orders/' . $order_id . '/capture',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '{}',
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
                error_log("Erreur capture paiement PayPal. Code: $http_code - Erreur: $curl_error - Réponse: $response");
                throw new Exception("Erreur lors de la capture du paiement PayPal", 500);
            }
            
            $capture = json_decode($response, true);
            
            if ($capture['status'] != 'COMPLETED') {
                throw new Exception("Paiement non complété. Statut: " . $capture['status'], 400);
            }
            
            // Commencer une transaction pour la mise à jour
            $db->beginTransaction();

            try {
                // Extraire les informations de paiement
                $payment_id = null;
                $amount_captured = null;
                
                if (isset($capture['purchase_units'][0]['payments']['captures'][0])) {
                    $capture_info = $capture['purchase_units'][0]['payments']['captures'][0];
                    $payment_id = $capture_info['id'];
                    $amount_captured = $capture_info['amount']['value'];
                }
                
                // Mettre à jour la réservation
                $update_stmt = $db->prepare("UPDATE reservations SET 
                    statut = 'confirme',
                    paypal_payment_id = ?,
                    transaction_id = ?,
                    confirmed_at = NOW(),
                    expires_at = NULL
                    WHERE id = ?");
                
                $update_result = $update_stmt->execute([
                    $payment_id,
                    $order_id,
                    $reservation['id']
                ]);
                
                if (!$update_result || $update_stmt->rowCount() == 0) {
                    throw new Exception("Impossible de mettre à jour la réservation", 500);
                }
                
                $db->commit();
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Paiement confirmé avec succès",
                    "reservation_id" => $reservation['id'],
                    "payment_id" => $payment_id,
                    "order_id" => $order_id,
                    "amount_captured" => $amount_captured
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } else {
            throw new Exception("Action non reconnue ou paramètres manquants", 400);
        }
        
    } catch (Exception $e) {
        error_log("Erreur PayPal: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
        
        $error_code = $e->getCode() ?: 500;
        http_response_code($error_code);
        
        $error_response = [
            "status" => "error",
            "message" => $e->getMessage(),
            "code" => $error_code
        ];
        
        echo json_encode($error_response);
    }
} else {
    http_response_code(405);
    echo json_encode(["message" => "Méthode non autorisée"]);
}
?>