<?php
class Lista {
    private $conn;
    private $table_name = "listas";
    private $table_itens = "itens_lista";

    public $id;
    public $id_user;
    public $titulo;
    public $cor;
    public $ativo;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listar($user_id) {
        $query = "SELECT 
                    l.id,
                    l.titulo,
                    l.cor,
                    l.ativo,
                    l.data_criacao,
                    COUNT(i.id) as total_itens,
                    SUM(CASE WHEN i.concluido = 1 THEN 1 ELSE 0 END) as itens_concluidos
                  FROM " . $this->table_name . " l
                  LEFT JOIN " . $this->table_itens . " i ON l.id = i.id_lista
                  WHERE l.id_user = ? AND l.ativo = 1
                  GROUP BY l.id
                  ORDER BY l.data_criacao DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($user_id, $dados) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_user, titulo, cor, ativo) 
                  VALUES (?, ?, ?, 1)";

        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute([
            $user_id,
            $dados['titulo'],
            $dados['cor'] ?? 'blue'
        ])) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function atualizar($id, $user_id, $dados) {
        $query = "UPDATE " . $this->table_name . " 
                  SET titulo = ?, cor = ?
                  WHERE id = ? AND id_user = ?";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $dados['titulo'],
            $dados['cor'] ?? 'blue',
            $id,
            $user_id
        ]);
    }

    public function deletar($id, $user_id) {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id = ? AND id_user = ?";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id, $user_id]);
    }

    public function obterPorId($id, $user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id = ? AND id_user = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id, $user_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listarItens($id_lista, $user_id) {
        $query = "SELECT i.* 
                  FROM " . $this->table_itens . " i
                  INNER JOIN " . $this->table_name . " l ON i.id_lista = l.id
                  WHERE i.id_lista = ? AND l.id_user = ?
                  ORDER BY i.ordem ASC, i.id ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_lista, $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adicionarItem($id_lista, $user_id, $texto) {
        $lista = $this->obterPorId($id_lista, $user_id);
        if (!$lista) {
            return false;
        }

        $query_ordem = "SELECT COALESCE(MAX(ordem), 0) + 1 as proxima_ordem 
                        FROM " . $this->table_itens . " 
                        WHERE id_lista = ?";
        $stmt_ordem = $this->conn->prepare($query_ordem);
        $stmt_ordem->execute([$id_lista]);
        $ordem = $stmt_ordem->fetch(PDO::FETCH_ASSOC)['proxima_ordem'];

        $query = "INSERT INTO " . $this->table_itens . " 
                  (id_lista, texto, ordem) 
                  VALUES (?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute([$id_lista, $texto, $ordem])) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function atualizarItem($id_item, $id_lista, $user_id, $dados) {
        $lista = $this->obterPorId($id_lista, $user_id);
        if (!$lista) {
            return false;
        }

        $query = "UPDATE " . $this->table_itens . " 
                  SET texto = ?, concluido = ?" .
                  (isset($dados['concluido']) && $dados['concluido'] ? 
                    ", data_conclusao = NOW()" : ", data_conclusao = NULL") . "
                  WHERE id = ? AND id_lista = ?";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $dados['texto'],
            $dados['concluido'] ? 1 : 0,
            $id_item,
            $id_lista
        ]);
    }

    public function deletarItem($id_item, $id_lista, $user_id) {
        $lista = $this->obterPorId($id_lista, $user_id);
        if (!$lista) {
            return false;
        }

        $query = "DELETE FROM " . $this->table_itens . " 
                  WHERE id = ? AND id_lista = ?";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_item, $id_lista]);
    }

    public function toggleItemConcluido($id_item, $id_lista, $user_id) {
        $lista = $this->obterPorId($id_lista, $user_id);
        if (!$lista) {
            return false;
        }

        $query = "UPDATE " . $this->table_itens . " 
                  SET concluido = NOT concluido,
                      data_conclusao = CASE WHEN concluido = 0 THEN NOW() ELSE NULL END
                  WHERE id = ? AND id_lista = ?";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_item, $id_lista]);
    }
}
?>