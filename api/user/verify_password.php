<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';
include_once '../../models/User.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id) || empty($data->password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID e senha são obrigatórios"
    ]);
    exit();
}

try {
    $user->id = $data->id;
    $stmt = $user->findById();
    
    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Usuário não encontrado"
        ]);
        exit();
    }

    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($data->password, $currentUser['senha'])) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "valid" => true,
            "message" => "Senha válida"
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "valid" => false,
            "message" => "Senha inválida"
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao verificar senha: " . $e->getMessage()
    ]);
}
?>