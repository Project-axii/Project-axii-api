<?php
class Device {
    private $conn;
    private $table_name = "dispositivos";

    public $id;
    public $id_user;
    public $ip;
    public $tipo;
    public $nome;
    public $descricao;
    public $status;
    public $sala;
    public $ativo;
    public $data_cadastro;
    public $ultima_conexao;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todos os dispositivos do usuário
    public function getByUser($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id_user = :user_id 
                  ORDER BY sala, nome";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        return $stmt;
    }

    // Buscar dispositivo por ID
    public function getById() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id = :id AND id_user = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->id_user);
        $stmt->execute();

        return $stmt;
    }

    // Criar novo dispositivo
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET id_user = :id_user,
                      ip = :ip,
                      tipo = :tipo,
                      nome = :nome,
                      descricao = :descricao,
                      sala = :sala,
                      status = :status,
                      ativo = :ativo";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->ip = htmlspecialchars(strip_tags($this->ip));
        $this->tipo = htmlspecialchars(strip_tags($this->tipo));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));
        $this->sala = htmlspecialchars(strip_tags($this->sala));

        // Bind dos valores
        $stmt->bindParam(":id_user", $this->id_user);
        $stmt->bindParam(":ip", $this->ip);
        $stmt->bindParam(":tipo", $this->tipo);
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":descricao", $this->descricao);
        $stmt->bindParam(":sala", $this->sala);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":ativo", $this->ativo);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    // Atualizar dispositivo
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET nome = :nome,
                      ip = :ip,
                      tipo = :tipo,
                      descricao = :descricao,
                      sala = :sala,
                      status = :status,
                      ativo = :ativo
                  WHERE id = :id AND id_user = :user_id";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->ip = htmlspecialchars(strip_tags($this->ip));
        $this->tipo = htmlspecialchars(strip_tags($this->tipo));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));
        $this->sala = htmlspecialchars(strip_tags($this->sala));

        // Bind dos valores
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":ip", $this->ip);
        $stmt->bindParam(":tipo", $this->tipo);
        $stmt->bindParam(":descricao", $this->descricao);
        $stmt->bindParam(":sala", $this->sala);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":ativo", $this->ativo);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->id_user);

        return $stmt->execute();
    }

    // Atualizar apenas o status
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = :status,
                      ultima_conexao = CURRENT_TIMESTAMP
                  WHERE id = :id AND id_user = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->id_user);

        return $stmt->execute();
    }

    // Toggle ativo/inativo
    public function toggleActive() {
        $query = "UPDATE " . $this->table_name . "
                  SET ativo = NOT ativo
                  WHERE id = :id AND id_user = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->id_user);

        return $stmt->execute();
    }

    // Deletar dispositivo
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id = :id AND id_user = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->id_user);

        return $stmt->execute();
    }

    // Verificar se IP já existe
    public function ipExists() {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE ip = :ip AND id_user = :user_id AND id != :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":ip", $this->ip);
        $stmt->bindParam(":user_id", $this->id_user);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // Obter estatísticas dos dispositivos
    public function getStatsByUser($user_id) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
                    SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline,
                    SUM(CASE WHEN status = 'manutencao' THEN 1 ELSE 0 END) as manutencao
                  FROM " . $this->table_name . "
                  WHERE id_user = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        return $stmt;
    }

    // Listar dispositivos por sala
    public function getByRoom($user_id, $sala) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id_user = :user_id AND sala = :sala
                  ORDER BY nome";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":sala", $sala);
        $stmt->execute();

        return $stmt;
    }

    // Listar todas as salas do usuário
    public function getRoomsByUser($user_id) {
        $query = "SELECT DISTINCT sala, 
                    COUNT(*) as total_dispositivos,
                    SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
                    SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
                  FROM " . $this->table_name . "
                  WHERE id_user = :user_id AND sala IS NOT NULL AND sala != ''
                  GROUP BY sala
                  ORDER BY sala";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        return $stmt;
    }
}
?>