<?php
class AuthMiddleware {
    // Collez votre clé ici ▼
    private $secret_key = "13eafebe1deb353ada19e2895b614048"; 
    
    public function authenticateRequest() {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($auth_header)) {
            return false;
        }
        
        if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        
        try {
            // Décodage simple (adapté à votre ancienne méthode)
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded || empty($decoded['user_id']) || empty($decoded['exp'])) {
                return false;
            }
            
            if ($decoded['exp'] < time()) {
                return false;
            }
            
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>