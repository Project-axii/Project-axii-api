<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/database.php';
include_once '../models/Device.php';

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token não fornecido"
    ]);
    exit();
}

try {
    $decoded = json_decode(base64_decode($token));
    
    if (!$decoded || !isset($decoded->id) || $decoded->exp < time()) {
        throw new Exception("Token inválido ou expirado");
    }
    
    $user_id = $decoded->id;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token inválido ou expirado"
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$device = new Device($db);

$stmt = $device->getRoomsByUser($user_id);
$num = $stmt->rowCount();

if ($num > 0) {
    $rooms_arr = array();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $room_item = array(
            "name" => $row['sala'],
            "devices" => (int)$row['total_dispositivos'],
            "online" => (int)$row['online'],
            "offline" => (int)$row['offline']
        );
        
        array_push($rooms_arr, $room_item);
    }
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $rooms_arr,
        "total" => $num
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => [],
        "total" => 0,
        "message" => "Nenhuma sala encontrada"
    ]);
}
?>