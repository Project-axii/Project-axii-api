<?php
class User {
    private $conn;
    private $table_name = "usuario";

    public $id;
    public $nome;
    public $email;
    public $senha;
    public $foto;
    public $tipo_usuario;
    public $ativo;
    public $data_criacao;
    public $data_atualizacao;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    nome = :nome,
                    email = :email,
                    senha = :senha,
                    tipo_usuario = :tipo_usuario,
                    ativo = :ativo";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->tipo_usuario = htmlspecialchars(strip_tags($this->tipo_usuario));

        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":senha", $this->senha);
        $stmt->bindParam(":tipo_usuario", $this->tipo_usuario);
        $stmt->bindParam(":ativo", $this->ativo);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    public function findByEmail() {
        $query = "SELECT 
                    id, nome, email, senha, foto, tipo_usuario, ativo
                FROM 
                    " . $this->table_name . "
                WHERE 
                    email = :email
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();

        return $stmt;
    }

    public function findById() {
        $query = "SELECT 
                    id, nome, email, foto, tipo_usuario, ativo
                FROM 
                    " . $this->table_name . "
                WHERE 
                    id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        return $stmt;
    }

    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . "
                SET data_atualizacao = CURRENT_TIMESTAMP
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    nome = :nome,
                    email = :email";

        if (!empty($this->senha)) {
            $query .= ", senha = :senha";
        }

        if (!empty($this->foto)) {
            $query .= ", foto = :foto";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->email = htmlspecialchars(strip_tags($this->email));

        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":id", $this->id);

        if (!empty($this->senha)) {
            $stmt->bindParam(":senha", $this->senha);
        }

        if (!empty($this->foto)) {
            $stmt->bindParam(":foto", $this->foto);
        }

        return $stmt->execute();
    }

    public function delete() {
        $query = "UPDATE " . $this->table_name . "
                SET ativo = 0
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function readAll() {
        $query = "SELECT 
                    id, nome, email, foto, tipo_usuario, ativo, data_criacao
                FROM 
                    " . $this->table_name . "
                WHERE 
                    ativo = 1
                ORDER BY 
                    nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function emailExists() {
        $query = "SELECT id 
                FROM " . $this->table_name . "
                WHERE email = :email
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
?>