<?php
// backend/api/auth/register.php

// Debug (à désactiver en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers CORS stricts
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Répondre immédiatement aux requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require __DIR__.'/../../config/database.php';

try {
    // Connexion DB avec gestion d'erreur
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupération et validation des données
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Format JSON invalide", 400);
    }
    
    // Validation des champs requis
    $requiredFields = ['nom', 'email', 'telephone', 'password'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Le champ $field est requis", 400);
        }
    }
    
    // Nettoyage et validation de l'email
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Format d'email invalide", 400);
    }
    
    // Vérification de l'unicité de l'email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        throw new Exception("Un compte existe déjà avec cet email", 409);
    }
    
    // Hashage sécurisé du mot de passe
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    if ($passwordHash === false) {
        throw new Exception("Erreur lors du hashage du mot de passe", 500);
    }
    
    // Insertion de l'utilisateur
    $stmt = $db->prepare("
        INSERT INTO users 
        (nom, email, telephone, password_hash) 
        VALUES 
        (:nom, :email, :telephone, :password_hash)
    ");
    
    $stmt->bindParam(':nom', $data['nom'], PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':telephone', $data['telephone'], PDO::PARAM_STR);
    $stmt->bindParam(':password_hash', $passwordHash, PDO::PARAM_STR);
    
    if (!$stmt->execute()) {
        throw new Exception("Échec de la création de l'utilisateur", 500);
    }
    
    $userId = $db->lastInsertId();
    
    // Génération du token (simplifié - utiliser une lib JWT en production)
    $tokenData = [
        'user_id' => $userId,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60) // 24h
    ];
    $token = base64_encode(json_encode($tokenData));
    
    // Réponse de succès
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Compte créé avec succès',
        'token' => $token,
        'user' => [
            'id' => $userId,
            'nom' => $data['nom'],
            'email' => $email,
            'telephone' => $data['telephone']
        ],
        'expires_in' => 86400 // 24h en secondes
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données',
        'error_code' => 'DB_ERROR',
        'error_details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 400;
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'REGISTER_ERROR',
        'error_details' => $statusCode === 500 ? null : $e->getMessage()
    ]);
}
?>