<?php
class Rotina {
    private $conn;
    private $table = 'agendamentos';

    public $id;
    public $id_user;
    public $id_dispositivo;
    public $id_grupo;
    public $nome;
    public $descricao;
    public $horario_ini;
    public $horario_fim;
    public $dias_semana;
    public $acao;
    public $parametros;
    public $ativo;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($user_id) {
        $query = "SELECT 
                    a.*,
                    CASE 
                        WHEN a.id_dispositivo IS NOT NULL THEN d.nome
                        WHEN a.id_grupo IS NOT NULL THEN g.nome
                        ELSE 'Sem alvo'
                    END as alvo_nome,
                    CASE 
                        WHEN a.id_dispositivo IS NOT NULL THEN 'dispositivo'
                        WHEN a.id_grupo IS NOT NULL THEN 'grupo'
                        ELSE NULL
                    END as alvo_tipo
                FROM " . $this->table . " a
                LEFT JOIN dispositivos d ON a.id_dispositivo = d.id
                LEFT JOIN grupo g ON a.id_grupo = g.id
                WHERE a.id_user = :user_id
                ORDER BY a.ativo DESC, a.horario_ini ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt;
    }

    public function getById($id, $user_id) {
        $query = "SELECT 
                    a.*,
                    CASE 
                        WHEN a.id_dispositivo IS NOT NULL THEN d.nome
                        WHEN a.id_grupo IS NOT NULL THEN g.nome
                        ELSE 'Sem alvo'
                    END as alvo_nome,
                    CASE 
                        WHEN a.id_dispositivo IS NOT NULL THEN 'dispositivo'
                        WHEN a.id_grupo IS NOT NULL THEN 'grupo'
                        ELSE NULL
                    END as alvo_tipo
                FROM " . $this->table . " a
                LEFT JOIN dispositivos d ON a.id_dispositivo = d.id
                LEFT JOIN grupo g ON a.id_grupo = g.id
                WHERE a.id = :id AND a.id_user = :user_id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . "
                SET
                    id_user = :id_user,
                    id_dispositivo = :id_dispositivo,
                    id_grupo = :id_grupo,
                    nome = :nome,
                    descricao = :descricao,
                    horario_ini = :horario_ini,
                    horario_fim = :horario_fim,
                    dias_semana = :dias_semana,
                    acao = :acao,
                    parametros = :parametros,
                    ativo = :ativo";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));
        
        $stmt->bindParam(':id_user', $this->id_user);
        $stmt->bindParam(':id_dispositivo', $this->id_dispositivo);
        $stmt->bindParam(':id_grupo', $this->id_grupo);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':descricao', $this->descricao);
        $stmt->bindParam(':horario_ini', $this->horario_ini);
        $stmt->bindParam(':horario_fim', $this->horario_fim);
        $stmt->bindParam(':dias_semana', $this->dias_semana);
        $stmt->bindParam(':acao', $this->acao);
        $stmt->bindParam(':parametros', $this->parametros);
        $stmt->bindParam(':ativo', $this->ativo);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table . "
                SET
                    id_dispositivo = :id_dispositivo,
                    id_grupo = :id_grupo,
                    nome = :nome,
                    descricao = :descricao,
                    horario_ini = :horario_ini,
                    horario_fim = :horario_fim,
                    dias_semana = :dias_semana,
                    acao = :acao,
                    parametros = :parametros,
                    ativo = :ativo
                WHERE
                    id = :id AND id_user = :id_user";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':id_user', $this->id_user);
        $stmt->bindParam(':id_dispositivo', $this->id_dispositivo);
        $stmt->bindParam(':id_grupo', $this->id_grupo);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':descricao', $this->descricao);
        $stmt->bindParam(':horario_ini', $this->horario_ini);
        $stmt->bindParam(':horario_fim', $this->horario_fim);
        $stmt->bindParam(':dias_semana', $this->dias_semana);
        $stmt->bindParam(':acao', $this->acao);
        $stmt->bindParam(':parametros', $this->parametros);
        $stmt->bindParam(':ativo', $this->ativo);

        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table . " 
                WHERE id = :id AND id_user = :id_user";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':id_user', $this->id_user);

        return $stmt->execute();
    }

     public function toggleAtivo($id, $user_id) {
        $query = "UPDATE " . $this->table . "
                SET ativo = CASE 
                    WHEN ativo = 1 THEN 0
                    ELSE 1
                END
                WHERE id = :id AND id_user = :id_user";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function executar($id, $user_id) {
        $stmt = $this->getById($id, $user_id);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $log_query = "INSERT INTO logs_atividades (id_user, acao, descricao)
                     VALUES (:user_id, :acao, :descricao)";
        
        $log_stmt = $this->conn->prepare($log_query);
        $acao_log = 'EXECUTAR_ROTINA';
        $descricao = "Rotina '{$row['nome']}' executada manualmente";
        
        $log_stmt->bindParam(':user_id', $user_id);
        $log_stmt->bindParam(':acao', $acao_log);
        $log_stmt->bindParam(':descricao', $descricao);
        $log_stmt->execute();


        return true;
    }

    public function getDispositivosDisponiveis($user_id) {
        $query = "SELECT id, nome, tipo, sala 
                FROM dispositivos 
                WHERE id_user = :user_id AND ativo = 1
                ORDER BY sala, nome";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt;
    }

    public function getGruposDisponiveis($user_id) {
        $query = "SELECT id, nome, descricao, cor 
                FROM grupo 
                WHERE id_user = :user_id AND ativo = 1
                ORDER BY nome";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt;
    }
}
?>