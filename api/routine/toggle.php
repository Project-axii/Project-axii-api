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
    include_once '../../models/routine.php';

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
    $id = $data->id ?? 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID inválido"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $rotina = new Rotina($db);

    if ($rotina->toggleAtivo($id, $user_id)) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Status alterado com sucesso"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(503);
        echo json_encode([
            "success" => false,
            "message" => "Não foi possível alterar o status"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("ERRO EM ROTINAS/TOGGLE.PHP: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>