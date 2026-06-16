<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
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

    $input = json_decode(file_get_contents("php://input"), true);
    $id    = isset($input["id"]) ? (int)$input["id"] : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID da lista é obrigatório"]);
        exit();
    }

    $database = new Database();
    $db       = $database->getConnection();

    $lista            = new Lista($db);
    $lista_existente  = $lista->obterPorId($id, $user_id);

    if (!$lista_existente) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Lista não encontrada"]);
        exit();
    }

    if ($lista->deletar($id, $user_id)) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Lista deletada com sucesso"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("Erro ao deletar lista");
    }

} catch (Exception $e) {
    error_log("ERRO EM delete.php (list): " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>
