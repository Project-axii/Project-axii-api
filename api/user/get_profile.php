<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';
include_once '../../models/User.php';
include_once '../middleware/auth.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

// Verificação de autenticação obrigatória
$authUser = requireAuth();

// IDOR: usuário só pode ver o próprio perfil
$userId = $authUser['id'];

try {
    $user->id = $userId;
    $stmt = $user->findById();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "user" => [
                "id"              => $row['id'],
                "nome"            => $row['nome'],
                "email"           => $row['email'],
                "foto"            => $row['foto'],
                "tipo_usuario"    => $row['tipo_usuario'],
                "ativo"           => $row['ativo'],
                "data_criacao"    => $row['data_criacao'] ?? null,
                "data_atualizacao"=> $row['data_atualizacao'] ?? null
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
    error_log("Erro em get_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao buscar perfil"
    ]);
}
?>
