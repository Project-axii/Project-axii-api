<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include_once '../../config/database.php';
    include_once '../../models/Device.php';

    $token = null;
    
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    
    if (!$token && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    
    if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    
    if (!$token && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

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
        
        if (!$decoded || !isset($decoded->id) || !isset($decoded->exp)) {
            throw new Exception("Token inválido");
        }
        
        if ($decoded->exp < time()) {
            throw new Exception("Token expirado");
        }
        
        $user_id = $decoded->id;
        
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token inválido ou expirado",
            "error" => $e->getMessage()
        ]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"));

    if (!$data || !isset($data->id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID do dispositivo não fornecido"
        ]);
        exit();
    }

    $device_id = $data->id;

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }

    $select_query = "SELECT id, nome, status, ativo FROM dispositivos WHERE id = ? AND id_user = ?";
    $select_stmt = $db->prepare($select_query);
    $select_stmt->execute([$device_id, $user_id]);
    
    if ($select_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Dispositivo não encontrado ou sem permissão"
        ]);
        exit();
    }

    $device = $select_stmt->fetch(PDO::FETCH_ASSOC);
    $current_status = $device['status'];
    $current_ativo = $device['ativo'];

    if (isset($data->action)) {
        switch ($data->action) {
            case 'toggle_status':
                $new_status = ($current_status === 'online') ? 'offline' : 'online';
                $update_query = "UPDATE dispositivos 
                                SET status = ?, ultima_conexao = CURRENT_TIMESTAMP 
                                WHERE id = ? AND id_user = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$new_status, $device_id, $user_id]);
                
                $log_desc = "Status alterado de {$current_status} para {$new_status}";
                $action_log = 'TOGGLE_STATUS';
                break;
                
            case 'toggle_active':
                $new_ativo = $current_ativo ? 0 : 1;
                $update_query = "UPDATE dispositivos 
                                SET ativo = ? 
                                WHERE id = ? AND id_user = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$new_ativo, $device_id, $user_id]);
                
                $log_desc = "Dispositivo " . ($new_ativo ? "ativado" : "desativado");
                $action_log = 'TOGGLE_DEVICE';
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Ação inválida. Use 'toggle_status' ou 'toggle_active'"
                ]);
                exit();
        }
    } else {
        $new_status = ($current_status === 'online') ? 'offline' : 'online';
        $update_query = "UPDATE dispositivos 
                        SET status = ?, ultima_conexao = CURRENT_TIMESTAMP 
                        WHERE id = ? AND id_user = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$new_status, $device_id, $user_id]);
        
        $log_desc = "Status alterado de {$current_status} para {$new_status}";
        $action_log = 'TOGGLE_STATUS';
    }

    $log_query = "INSERT INTO logs_atividades (id_user, id_dispositivo, acao, descricao, sucesso) 
                  VALUES (?, ?, ?, ?, 1)";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->execute([
        $user_id,
        $device_id,
        $action_log,
        $log_desc
    ]);

    $final_query = "SELECT id, nome, ip, tipo, sala, descricao, status, ativo, 
                    data_cadastro, ultima_conexao 
                    FROM dispositivos WHERE id = ?";
    $final_stmt = $db->prepare($final_query);
    $final_stmt->execute([$device_id]);
    $updated_device = $final_stmt->fetch(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Dispositivo alternado com sucesso",
        "data" => [
            "id" => (int)$updated_device['id'],
            "nome" => $updated_device['nome'],
            "ip" => $updated_device['ip'],
            "tipo" => $updated_device['tipo'],
            "sala" => $updated_device['sala'],
            "descricao" => $updated_device['descricao'] ?? '',
            "status" => $updated_device['status'],
            "ativo" => (bool)$updated_device['ativo'],
            "data_cadastro" => $updated_device['data_cadastro'],
            "ultima_conexao" => $updated_device['ultima_conexao']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("ERRO EM TOGGLE.PHP: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>