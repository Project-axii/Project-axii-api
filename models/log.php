<?php
class Log {
    private $conn;
    private $table_name = "logs_atividades";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($id_user, $acao, $descricao, $ip_origem, $sucesso = 1) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_user, acao, descricao, ip_origem, sucesso) 
                  VALUES (:id_user, :acao, :descricao, :ip_origem, :sucesso)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id_user", $id_user);
        $stmt->bindParam(":acao", $acao);
        $stmt->bindParam(":descricao", $descricao);
        $stmt->bindParam(":ip_origem", $ip_origem);
        $stmt->bindParam(":sucesso", $sucesso);

        return $stmt->execute();
    }
}
?>