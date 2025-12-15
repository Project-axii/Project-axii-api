<?php
class Notification {
    private $conn;
    private $table_name = "notificacoes";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($id_user, $tipo, $titulo, $mensagem, $id_dispositivo = null) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_user, id_dispositivo, tipo, titulo, mensagem) 
                  VALUES (:id_user, :id_dispositivo, :tipo, :titulo, :mensagem)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id_user", $id_user);
        $stmt->bindParam(":id_dispositivo", $id_dispositivo);
        $stmt->bindParam(":tipo", $tipo);
        $stmt->bindParam(":titulo", $titulo);
        $stmt->bindParam(":mensagem", $mensagem);

        return $stmt->execute();
    }
}
?>