<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
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

    $check_query = "SELECT id FROM dispositivos WHERE id = ? AND id_user = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$device_id, $user_id]);
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Dispositivo não encontrado ou sem permissão"
        ]);
        exit();
    }

    $update_fields = [];
    $update_values = [];

    if (isset($data->nome)) {
        $update_fields[] = "nome = ?";
        $update_values[] = $data->nome;
    }

    if (isset($data->ip)) {
        $ip_check = $db->prepare("SELECT id FROM dispositivos WHERE ip = ? AND id_user = ? AND id != ?");
        $ip_check->execute([$data->ip, $user_id, $device_id]);
        
        if ($ip_check->rowCount() > 0) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "Já existe outro dispositivo com este IP"
            ]);
            exit();
        }
        
        $update_fields[] = "ip = ?";
        $update_values[] = $data->ip;
    }

    if (isset($data->tipo)) {
        $tipos_validos = ['computador', 'projetor', 'iluminacao', 'ar_condicionado', 'outro'];
        if (!in_array($data->tipo, $tipos_validos)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Tipo de dispositivo inválido"
            ]);
            exit();
        }
        $update_fields[] = "tipo = ?";
        $update_values[] = $data->tipo;
    }

    if (isset($data->sala)) {
        $update_fields[] = "sala = ?";
        $update_values[] = $data->sala;
    }

    if (isset($data->descricao)) {
        $update_fields[] = "descricao = ?";
        $update_values[] = $data->descricao;
    }

    if (isset($data->status)) {
        $status_validos = ['online', 'offline', 'manutencao'];
        if (!in_array($data->status, $status_validos)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Status inválido"
            ]);
            exit();
        }
        $update_fields[] = "status = ?";
        $update_values[] = $data->status;
    }

    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Nenhum campo para atualizar"
        ]);
        exit();
    }

    $update_query = "UPDATE dispositivos SET " . implode(", ", $update_fields) . " WHERE id = ? AND id_user = ?";
    $update_values[] = $device_id;
    $update_values[] = $user_id;
    
    $update_stmt = $db->prepare($update_query);
    
    if ($update_stmt->execute($update_values)) {
        $log_query = "INSERT INTO logs_atividades (id_user, id_dispositivo, acao, descricao, sucesso) 
                      VALUES (?, ?, 'UPDATE_DEVICE', ?, 1)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            $user_id,
            $device_id,
            "Dispositivo atualizado"
        ]);

        $select_query = "SELECT id, nome, ip, tipo, sala, descricao, status, ativo, 
                         data_cadastro, ultima_conexao 
                         FROM dispositivos WHERE id = ?";
        $select_stmt = $db->prepare($select_query);
        $select_stmt->execute([$device_id]);
        $updated_device = $select_stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Dispositivo atualizado com sucesso",
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
    } else {
        throw new Exception("Erro ao atualizar dispositivo");
    }

} catch (Exception $e) {
    error_log("ERRO EM UPDATE.PHP: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>