<?php
// backend/api/auth/user.php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Content-Type: application/json; charset=UTF-8");

require __DIR__.'/../../config/database.php';
require __DIR__.'/authMiddleware.php';

try {
    $auth = new AuthMiddleware();
    $user_data = $auth->authenticateRequest();
    
    if (!$user_data) {
        throw new Exception("Token invalide ou expiré", 401);
    }
    
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT id, nom, email, telephone, points_fidelite FROM users WHERE id = ?");
    $stmt->execute([$user_data['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("Utilisateur non trouvé", 404);
    }
    
    http_response_code(200);
    echo json_encode($user);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}