<?php
// backend/api/auth/login.php

// Debug (à désactiver en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// backend/api/auth/login.php

// Headers CORS stricts
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Répondre immédiatement aux requêtes OPTIONS (pré-vol)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}


require __DIR__.'/../../config/database.php'; // Chemin relatif corrigé

try {
    // Connexion DB avec timeout
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation robuste
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Format JSON invalide", 400);
    }
    
    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception("Email et mot de passe requis", 400);
    }

    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    
    // Requête sécurisée
    $stmt = $db->prepare("SELECT id, nom, email, telephone, password_hash, points_fidelite 
                         FROM users 
                         WHERE email = :email 
                         LIMIT 1");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Compte non trouvé", 404);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérification mot de passe
    if (!password_verify($data['password'], $user['password_hash'])) {
        throw new Exception("Mot de passe incorrect", 401);
    }
    
    // Génération du token adapté à votre structure
    $tokenData = [
        'user_id' => $user['id'],
        'nom' => $user['nom'],
        'email' => $user['email'],
        'points' => $user['points_fidelite'],
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60) // 24h
    ];
    
    $token = base64_encode(json_encode($tokenData));
    
    // Réponse complète
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'nom' => $user['nom'],
            'email' => $user['email'],
            'telephone' => $user['telephone'],
            'points_fidelite' => $user['points_fidelite']
        ],
        'expires_in' => 86400
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données',
        'error_code' => 'DB_ERROR'
    ]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'AUTH_ERROR'
    ]);
}