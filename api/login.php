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

if (empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "E-mail e senha são obrigatórios"
    ]);
    exit();
}

$user->email = $data->email;
$stmt = $user->findByEmail();
$num = $stmt->rowCount();

if ($num > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (password_verify($data->password, $row['senha'])) {
        $user->id = $row['id'];
        $user->updateLastLogin();
        
        $ip_origem = $_SERVER['REMOTE_ADDR'];
        
        $log->create(
            $row['id'],
            'LOGIN_SUCESSO',
            'Login realizado com sucesso',
            $ip_origem,
            1
        );
        
        $notification->create(
            $row['id'],
            'info',
            'Bem-vindo!',
            'Você fez login com sucesso no sistema.'
        );
        
        $token = base64_encode(json_encode([
            'id' => $row['id'],
            'email' => $row['email'],
            'exp' => time() + (60 * 60 * 24) 
        ]));
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Login realizado com sucesso",
            "token" => $token,
            "user" => [
                "id" => $row['id'],
                "nome" => $row['nome'],
                "email" => $row['email'],
                "foto" => $row['foto'],
                "tipo_usuario" => $row['tipo_usuario']
            ]
        ]);
    } else {
        $ip_origem = $_SERVER['REMOTE_ADDR'];
        
        $log->create(
            $row['id'],
            'LOGIN_FALHO',
            'Senha incorreta',
            $ip_origem,
            0
        );
        
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "E-mail ou senha inválidos"
        ]);
    }
} else {
    $ip_origem = $_SERVER['REMOTE_ADDR'];
    
    $log->create(
        null,
        'LOGIN_FALHO',
        'Tentativa de login com email não cadastrado: ' . $data->email,
        $ip_origem,
        0
    );
    
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "E-mail ou senha inválidos"
    ]);
}
?>