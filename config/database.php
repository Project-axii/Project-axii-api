<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host     = getenv('DB_HOST')     ?: 'localhost';
        $this->db_name  = getenv('DB_NAME')     ?: 'axii';
        $this->username = getenv('DB_USER')     ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '45163789';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            error_log("Erro de conexão com o banco de dados: " . $exception->getMessage());
            http_response_code(503);
            echo json_encode([
                "success" => false,
                "message" => "Serviço temporariamente indisponível"
            ]);
            exit();
        }

        return $this->conn;
    }
}
?>
