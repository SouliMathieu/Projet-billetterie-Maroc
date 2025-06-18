<?php
// En-têtes de sécurité CSP complets
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'nonce-".bin2hex(random_bytes(16))."' https://*.paypal.com https://www.paypal.com https://www.paypalobjects.com https://www.google-analytics.com https://www.gstatic.com 'unsafe-inline'; "
    . "style-src 'self' https://*.paypal.com 'unsafe-inline'; "
    . "img-src 'self' https://*.paypal.com https://www.google-analytics.com https://www.facebook.com https://px.ads.linkedin.com data: https://*.googleusercontent.com; "
    . "connect-src 'self' https://*.paypal.com https://api-m.sandbox.paypal.com https://www.google-analytics.com; "
    . "frame-src https://*.paypal.com; "
    . "font-src 'self' data:; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self' https://*.paypal.com;");
class Database {
    private $host = "localhost";
    private $db_name = "billetterie";
    private $username = "root";
    private $password = "";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}
?>