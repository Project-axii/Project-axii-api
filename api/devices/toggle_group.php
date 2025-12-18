<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';
include_once '../../models/Device.php';

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

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->sala) || empty($data->sala)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Nome da sala é obrigatório"
    ]);
    exit();
}

if (!isset($data->action) || !in_array($data->action, ['ligar', 'desligar', 'toggle'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Ação inválida. Use: ligar, desligar ou toggle"
    ]);
    exit();
}

$sala = $data->sala;
$action = $data->action;
$tipo = isset($data->tipo) ? $data->tipo : null;

$database = new Database();
$db = $database->getConnection();
$device = new Device($db);

try {
    $query = "SELECT id, status FROM " . $device->getTableName() . " 
              WHERE id_user = :user_id AND sala = :sala AND ativo = 1";
    
    if ($tipo !== null) {
        $query .= " AND tipo = :tipo";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':sala', $sala);
    
    if ($tipo !== null) {
        $stmt->bindParam(':tipo', $tipo);
    }
    
    $stmt->execute();
    
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $num_devices = count($devices);
    
    if ($num_devices === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => $tipo !== null 
                ? "Nenhum dispositivo do tipo '$tipo' encontrado nesta sala"
                : "Nenhum dispositivo encontrado nesta sala"
        ]);
        exit();
    }
    
    $new_status = 'online';
    
    if ($action === 'toggle') {
        $has_online = false;
        foreach ($devices as $dev) {
            if ($dev['status'] === 'online' || $dev['status'] === '1') {
                $has_online = true;
                break;
            }
        }
        $new_status = $has_online ? 'offline' : 'online';
    } elseif ($action === 'desligar') {
        $new_status = 'offline';
    }
    
    $update_query = "UPDATE " . $device->getTableName() . " 
                     SET status = :status, ultima_conexao = NOW() 
                     WHERE id_user = :user_id AND sala = :sala AND ativo = 1";
    
    if ($tipo !== null) {
        $update_query .= " AND tipo = :tipo";
    }
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':user_id', $user_id);
    $update_stmt->bindParam(':sala', $sala);
    
    if ($tipo !== null) {
        $update_stmt->bindParam(':tipo', $tipo);
    }
    
    if ($update_stmt->execute()) {
        $affected_rows = $update_stmt->rowCount();
        
        $message = $tipo !== null 
            ? "Status da categoria atualizado com sucesso"
            : "Status do grupo atualizado com sucesso";
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => $message,
            "data" => [
                "sala" => $sala,
                "tipo" => $tipo,
                "action" => $action,
                "new_status" => $new_status,
                "devices_updated" => $affected_rows
            ]
        ]);
    } else {
        throw new Exception("Erro ao atualizar dispositivos");
    }
    
} catch (Exception $e) {
    error_log("ERRO EM TOGGLE_GROUP: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao alterar status",
        "error" => $e->getMessage()
    ]);
}
?>