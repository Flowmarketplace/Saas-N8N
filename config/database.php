<?php
// config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "automatizacion_saas";
    private $username = "automatizacion_saas";
    private $password = "TMRXUHeJYpd4qHXJWZB6";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Configuración de la API N8N
define('N8N_API_URL', 'https://n8n-n8n.sax8vb.easypanel.host/api/v1/workflows');
define('N8N_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI1MmQzNWUxNS0wOTA1LTQ5YTktYjdjNS0wMGRhZmNhZmUzMDMiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzUyMDgxMTIxLCJleHAiOjE3NTQ2MjU2MDB9.PfGL27rKNNs_3Q9l_KRIy0XUZk-JEQGw2mH_EMCfsfI');

// Iniciar sesión
session_start();
?>