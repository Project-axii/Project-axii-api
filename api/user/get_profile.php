<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

$userId = isset($_GET['id']) ? $_GET['id'] : null;

if (empty($userId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID do usuário é obrigatório"
    ]);
    exit();
}

try {
    $user->id = $userId;
    $stmt = $user->findById();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $row['id'],
                "nome" => $row['nome'],
                "email" => $row['email'],
                "foto" => $row['foto'],
                "tipo_usuario" => $row['tipo_usuario'],
                "ativo" => $row['ativo'],
                "data_criacao" => $row['data_criacao'],
                "data_atualizacao" => $row['data_atualizacao']
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Usuário não encontrado"
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao buscar perfil: " . $e->getMessage()
    ]);
}
?>