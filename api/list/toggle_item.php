<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include_once '../../config/database.php';
    include_once '../../models/list.php';
    include_once '../middleware/auth.php';

    $authUser = requireAuth();
    $user_id  = $authUser['id'];

    $data = json_decode(file_get_contents("php://input"));

    if (!$data || !isset($data->id) || !isset($data->id_lista)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID do item e ID da lista são obrigatórios"
        ]);
        exit();
    }

    $database = new Database();
    $db       = $database->getConnection();

    $lista = new Lista($db);

    if ($lista->toggleItemConcluido($data->id, $data->id_lista, $user_id)) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Status do item atualizado"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("Erro ao atualizar status do item");
    }

} catch (Exception $e) {
    error_log("ERRO EM toggle_item.php (list): " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>
