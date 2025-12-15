<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include_once '../../config/database.php';
    include_once '../../models/Device.php';

    $token = null;
    
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    
    if (!$token && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
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
        ]);
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
            "message" => "Token inválido ou expirado",
            "error" => $e->getMessage()
        ]);
        exit();
    }

    $device_id = null;
    
    if (isset($_GET['id'])) {
        $device_id = $_GET['id'];
    }
    
    if (!$device_id) {
        $data = json_decode(file_get_contents("php://input"));
        if ($data && isset($data->id)) {
            $device_id = $data->id;
        }
    }

    if (!$device_id) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID do dispositivo não fornecido"
        ]);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }

    $check_query = "SELECT id, nome FROM dispositivos WHERE id = ? AND id_user = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$device_id, $user_id]);
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Dispositivo não encontrado ou sem permissão"
        ]);
        exit();
    }

    $device = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $device_nome = $device['nome'];

    $db->beginTransaction();

    try {
        $log_query = "INSERT INTO logs_atividades (id_user, id_dispositivo, acao, descricao, sucesso) 
                      VALUES (?, ?, 'DELETE_DEVICE', ?, 1)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            $user_id,
            $device_id,
            "Dispositivo deletado: " . $device_nome
        ]);

        $delete_groups_query = "DELETE FROM grupo_dispositivo WHERE id_dispositivo = ?";
        $delete_groups_stmt = $db->prepare($delete_groups_query);
        $delete_groups_stmt->execute([$device_id]);

        $delete_query = "DELETE FROM dispositivos WHERE id = ? AND id_user = ?";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->execute([$device_id, $user_id]);

        $db->commit();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Dispositivo deletado com sucesso",
            "data" => [
                "id" => (int)$device_id,
                "nome" => $device_nome
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("ERRO EM DELETE.PHP: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>