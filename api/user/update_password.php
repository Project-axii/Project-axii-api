<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';
include_once '../../models/User.php';
include_once '../../models/Log.php';
include_once '../../models/Notification.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$log = new Log($db);
$notification = new Notification($db);

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID do usuário é obrigatório"
    ]);
    exit();
}

$senha_atual = $data->currentPassword ?? $data->senha_atual ?? null;
$senha_nova = $data->newPassword ?? $data->senha_nova ?? null;
$confirmar_senha = $data->confirmPassword ?? $data->confirmar_senha ?? null;

if (empty($senha_atual) || empty($senha_nova) || empty($confirmar_senha)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Todos os campos de senha são obrigatórios"
    ]);
    exit();
}

if ($senha_nova !== $confirmar_senha) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A nova senha e a confirmação não coincidem"
    ]);
    exit();
}

if (strlen($senha_nova) < 6) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A senha deve ter no mínimo 6 caracteres"
    ]);
    exit();
}

try {
    $query = "SELECT id, nome, email, senha, foto, tipo_usuario 
              FROM usuario 
              WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $data->id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Usuário não encontrado"
        ]);
        exit();
    }

    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($currentUser['senha'])) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Erro ao recuperar dados do usuário"
        ]);
        exit();
    }

    if (!password_verify($senha_atual, $currentUser['senha'])) {
        $ip_origem = $_SERVER['REMOTE_ADDR'];
        
        $log->create(
            $data->id,
            'ATUALIZAR_SENHA_ERRO',
            'Tentativa de alteração de senha com senha atual incorreta',
            $ip_origem,
            0
        );

        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Senha atual incorreta"
        ]);
        exit();
    }

    if (password_verify($senha_nova, $currentUser['senha'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "A nova senha deve ser diferente da senha atual"
        ]);
        exit();
    }

    $senha_hash = password_hash($senha_nova, PASSWORD_BCRYPT);

    $query = "UPDATE usuario 
              SET senha = :senha,
                  data_atualizacao = CURRENT_TIMESTAMP
              WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":senha", $senha_hash);
    $stmt->bindParam(":id", $data->id);

    if ($stmt->execute()) {
        $ip_origem = $_SERVER['REMOTE_ADDR'];
        
        $log->create(
            $data->id,
            'ATUALIZAR_SENHA',
            'Senha atualizada com sucesso',
            $ip_origem,
            1
        );
        
        $notification->create(
            $data->id,
            'aviso',
            'Senha Alterada',
            'Sua senha foi alterada com sucesso. Se você não realizou esta ação, entre em contato com o suporte imediatamente.'
        );

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Senha atualizada com sucesso"
        ]);
    } else {
        throw new Exception("Erro ao atualizar senha");
    }

} catch (Exception $e) {
    $ip_origem = $_SERVER['REMOTE_ADDR'];
    
    $log->create(
        $data->id ?? null,
        'ATUALIZAR_SENHA_ERRO',
        'Erro ao atualizar senha: ' . $e->getMessage(),
        $ip_origem,
        0
    );

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao atualizar senha: " . $e->getMessage()
    ]);
}
?>