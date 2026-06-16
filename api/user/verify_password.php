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

include_once '../../config/database.php';
include_once '../../models/User.php';
include_once '../middleware/auth.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

// Verificação de autenticação obrigatória
$authUser = requireAuth();

$data = json_decode(file_get_contents("php://input"));

if (empty($data->password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Senha é obrigatória"
    ]);
    exit();
}

// IDOR: usa sempre o ID do token autenticado
$userId = $authUser['id'];

try {
    $user->id = $userId;
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

    // Buscar senha — findById não retorna senha, fazemos query específica
    $stmtSenha = $db->prepare("SELECT senha FROM usuario WHERE id = :id LIMIT 1");
    $stmtSenha->bindParam(":id", $userId);
    $stmtSenha->execute();
    $senhaRow = $stmtSenha->fetch(PDO::FETCH_ASSOC);

    if (password_verify($data->password, $senhaRow['senha'])) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "valid"   => true,
            "message" => "Senha válida"
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "valid"   => false,
            "message" => "Senha inválida"
        ]);
    }

} catch (Exception $e) {
    error_log("Erro em verify_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao verificar senha"
    ]);
}
?>
