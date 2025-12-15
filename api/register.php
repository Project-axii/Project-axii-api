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
include_once '../models/User.php';
include_once '../models/Log.php';
include_once '../models/Notification.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$log = new Log($db);
$notification = new Notification($db);

$data = json_decode(file_get_contents("php://input"));

if (empty($data->name) || empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Nome, e-mail e senha são obrigatórios"
    ]);
    exit();
}

if (strlen($data->name) < 3) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Nome deve ter pelo menos 3 caracteres"
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

if (strlen($data->password) < 6) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Senha deve ter pelo menos 6 caracteres"
    ]);
    exit();
}

$user->email = $data->email;
$stmt = $user->findByEmail();

if ($stmt->rowCount() > 0) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "Este e-mail já está cadastrado"
    ]);
    exit();
}

$user->nome = $data->name;
$user->email = $data->email;
$user->senha = password_hash($data->password, PASSWORD_BCRYPT);
$user->tipo_usuario = 'professor'; 
$user->ativo = 1;

try {
    if ($user->create()) {
        $ip_origem = $_SERVER['REMOTE_ADDR'];
        
        $log->create(
            $user->id,
            'CADASTRO_USUARIO',
            'Novo usuário cadastrado: ' . $user->email,
            $ip_origem,
            1
        );
        
        $notification->create(
            $user->id,
            'sucesso',
            'Bem-vindo ao Sistema!',
            'Sua conta foi criada com sucesso. Você já pode começar a gerenciar seus dispositivos.'
        );
        
        $token = base64_encode(json_encode([
            'id' => $user->id,
            'email' => $user->email,
            'exp' => time() + (60 * 60 * 24)
        ]));
        
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Cadastro realizado com sucesso!",
            "token" => $token,
            "user" => [
                "id" => $user->id,
                "nome" => $user->nome,
                "email" => $user->email,
                "tipo_usuario" => $user->tipo_usuario
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Erro ao criar usuário. Tente novamente."
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro no servidor: " . $e->getMessage()
    ]);
}
?>