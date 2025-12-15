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

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->token)) {
    try {
        $decoded = json_decode(base64_decode($data->token));
        
        if ($decoded->exp < time()) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "Token expirado"
            ]);
            exit();
        }
        
        $user->id = $decoded->id;
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
                    "tipo_usuario" => $row['tipo_usuario']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "Usuário não encontrado"
            ]);
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token inválido"
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Token não fornecido"
    ]);
}
?>