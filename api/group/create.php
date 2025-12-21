<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include_once '../../config/database.php';
    
    $token = null;
    
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    
    if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    
    if (!$token && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token não fornecido"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $decoded = json_decode(base64_decode($token));
        
        if (!$decoded || !isset($decoded->id) || !isset($decoded->exp)) {
            throw new Exception("Token inválido");
        }
        
        if ($decoded->exp < time()) {
            throw new Exception("Token expirado");
        }
        
        $user_id = $decoded->id;
        
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token inválido ou expirado"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"));

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Dados inválidos"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (empty($data->nome)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Nome é obrigatório"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $db->beginTransaction();

    try {
        $query = "INSERT INTO grupo
                SET
                    id_user = :id_user,
                    nome = :nome,
                    descricao = :descricao,
                    cor = :cor,
                    ativo = :ativo";

        $stmt = $db->prepare($query);

        $nome = htmlspecialchars(strip_tags($data->nome));
        $descricao = isset($data->descricao) ? htmlspecialchars(strip_tags($data->descricao)) : '';
        $cor = isset($data->cor) ? htmlspecialchars(strip_tags($data->cor)) : '#3498db';
        $ativo = isset($data->ativo) ? $data->ativo : 1;

        $stmt->bindParam(':id_user', $user_id);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':cor', $cor);
        $stmt->bindParam(':ativo', $ativo);

        if (!$stmt->execute()) {
            throw new Exception("Erro ao criar grupo");
        }

        $grupo_id = $db->lastInsertId();

        if (isset($data->dispositivos) && is_array($data->dispositivos) && count($data->dispositivos) > 0) {
            $query_disp = "INSERT INTO grupo_dispositivo (id_grupo, id_dispositivo) VALUES (:id_grupo, :id_dispositivo)";
            $stmt_disp = $db->prepare($query_disp);

            foreach ($data->dispositivos as $id_dispositivo) {
                $stmt_disp->bindParam(':id_grupo', $grupo_id);
                $stmt_disp->bindParam(':id_dispositivo', $id_dispositivo);
                $stmt_disp->execute();
            }
        }

        $db->commit();

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Grupo criado com sucesso",
            "id" => $grupo_id
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("ERRO EM GRUPOS/CREATE.PHP: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>