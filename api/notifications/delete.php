<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
              (isset($headers['authorization']) ? $headers['authorization'] : null);

if (!$authHeader) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token não fornecido"
    ]);
    exit();
}

$token = str_replace('Bearer ', '', $authHeader);

try {
    $tokenParts = explode('.', $token);
    
    if (count($tokenParts) < 2) {
        $tokenData = json_decode(base64_decode($token), true);
    } else {
        $tokenData = json_decode(base64_decode($tokenParts[1]), true);
    }
    
    if (!isset($tokenData['id'])) {
        throw new Exception('ID não encontrado no token');
    }
    
    $userId = $tokenData['id'];
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token inválido: " . $e->getMessage()
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID da notificação é obrigatório"
    ]);
    exit();
}

$query = "DELETE FROM notificacoes WHERE id = :id AND id_user = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $data->id);
$stmt->bindParam(':user_id', $userId);

if ($stmt->execute()) {
    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Notificação removida com sucesso"
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Notificação não encontrada"
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao remover notificação"
    ]);
}
?>