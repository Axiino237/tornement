<?php
require_once __DIR__ . '/../includes/logger.php';
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: "db";
        $this->db_name = getenv('DB_NAME') ?: "gaming41tournament";
        $this->username = getenv('DB_USER') ?: "root";
        $this->password = getenv('DB_PASS') ?: "";
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $port = getenv('DB_PORT') ?: "6543";
            $dsn = "pgsql:host=" . $this->host . ";port=" . $port . ";dbname=" . $this->db_name . ";sslmode=require";
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            // Temporarily echoing the error to help you debug in production
            echo "Connection Error: " . $e->getMessage();
        }

        return $this->conn;
    }
}