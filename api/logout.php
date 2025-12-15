<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/database.php';
include_once '../models/Log.php';

$database = new Database();
$db = $database->getConnection();
$log = new Log($db);

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id)) {
    $ip_origem = $_SERVER['REMOTE_ADDR'];
    
    $log->create(
        $data->user_id,
        'LOGOUT',
        'Usuário saiu do sistema',
        $ip_origem,
        1
    );
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Logout realizado com sucesso"
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID de usuário não fornecido"
    ]);
}
?>