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

if (empty($data->nome) || empty($data->email)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Nome e e-mail são obrigatórios"
    ]);
    exit();
}

if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "E-mail inválido"
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

    if ($data->email !== $currentUser['email']) {
        $user->email = $data->email;
        $emailCheck = $user->findByEmail();
        
        if ($emailCheck->rowCount() > 0) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "Este e-mail já está em uso"
            ]);
            exit();
        }
    }

    $query = "UPDATE usuario 
              SET nome = :nome, 
                  email = :email,
                  data_atualizacao = CURRENT_TIMESTAMP
              WHERE id = :id";

    $stmt = $db->prepare($query);

    $stmt->bindParam(":nome", $data->nome);
    $stmt->bindParam(":email", $data->email);
    $stmt->bindParam(":id", $data->id);

    if ($stmt->execute()) {
        $ip_origem = $_SERVER['REMOTE_ADDR'];
        
        $log->create(
            $data->id,
            'ATUALIZAR_PERFIL',
            'Perfil atualizado com sucesso',
            $ip_origem,
            1
        );
        
        $notification->create(
            $data->id,
            'sucesso',
            'Perfil Atualizado',
            'Suas informações foram atualizadas com sucesso.'
        );

        $user->id = $data->id;
        $updatedStmt = $user->findById();
        $updatedUser = $updatedStmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Perfil atualizado com sucesso",
            "user" => [
                "id" => $updatedUser['id'],
                "nome" => $updatedUser['nome'],
                "email" => $updatedUser['email'],
                "foto" => $updatedUser['foto'],
                "tipo_usuario" => $updatedUser['tipo_usuario']
            ]
        ]);
    } else {
        throw new Exception("Erro ao atualizar perfil");
    }

} catch (Exception $e) {
    $ip_origem = $_SERVER['REMOTE_ADDR'];
    
    $log->create(
        $data->id ?? null,
        'ATUALIZAR_PERFIL_ERRO',
        'Erro ao atualizar perfil: ' . $e->getMessage(),
        $ip_origem,
        0
    );

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao atualizar perfil: " . $e->getMessage()
    ]);
}
?>