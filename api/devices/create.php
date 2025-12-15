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

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Dados inválidos"
        ]);
        exit();
    }

    if (empty($data->nome) || empty($data->ip) || empty($data->tipo) || empty($data->sala)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Campos obrigatórios: nome, ip, tipo, sala"
        ]);
        exit();
    }

    $tipos_validos = ['computador', 'projetor', 'iluminacao', 'ar_condicionado', 'outro'];
    if (!in_array($data->tipo, $tipos_validos)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Tipo de dispositivo inválido"
        ]);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }

    $device = new Device($db);

    $stmt = $db->prepare("SELECT id FROM dispositivos WHERE ip = ? AND id_user = ?");
    $stmt->execute([$data->ip, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Já existe um dispositivo com este IP"
        ]);
        exit();
    }

    $query = "INSERT INTO dispositivos 
              (id_user, ip, tipo, nome, descricao, sala, status, ativo) 
              VALUES (?, ?, ?, ?, ?, ?, 'offline', 1)";
    
    $stmt = $db->prepare($query);
    $descricao = isset($data->descricao) ? $data->descricao : '';
    
    if ($stmt->execute([
        $user_id,
        $data->ip,
        $data->tipo,
        $data->nome,
        $descricao,
        $data->sala
    ])) {
        $device_id = $db->lastInsertId();
        
        $log_query = "INSERT INTO logs_atividades (id_user, id_dispositivo, acao, descricao, sucesso) 
                      VALUES (?, ?, 'ADD_DEVICE', ?, 1)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            $user_id,
            $device_id,
            "Novo dispositivo: " . $data->nome
        ]);

        $select_query = "SELECT id, nome, ip, tipo, sala, descricao, status, ativo, 
                         data_cadastro, ultima_conexao 
                         FROM dispositivos WHERE id = ?";
        $select_stmt = $db->prepare($select_query);
        $select_stmt->execute([$device_id]);
        $created_device = $select_stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Dispositivo criado com sucesso",
            "data" => [
                "id" => (int)$created_device['id'],
                "nome" => $created_device['nome'],
                "ip" => $created_device['ip'],
                "tipo" => $created_device['tipo'],
                "sala" => $created_device['sala'],
                "descricao" => $created_device['descricao'] ?? '',
                "status" => $created_device['status'],
                "ativo" => (bool)$created_device['ativo'],
                "data_cadastro" => $created_device['data_cadastro'],
                "ultima_conexao" => $created_device['ultima_conexao']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("Erro ao criar dispositivo");
    }

} catch (Exception $e) {
    error_log("ERRO EM CREATE.PHP: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>