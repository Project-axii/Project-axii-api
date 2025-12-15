<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

    error_log("=== LIST.PHP DEBUG ===");
    error_log("Token recebido: " . ($token ? substr($token, 0, 20) . "..." : "NENHUM"));
    error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("HTTP_AUTHORIZATION: " . (isset($_SERVER['HTTP_AUTHORIZATION']) ? "SIM" : "NÃO"));

    if (!$token) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token não fornecido",
            "debug" => [
                "method" => $_SERVER['REQUEST_METHOD'],
                "headers_available" => function_exists('getallheaders'),
                "server_auth" => isset($_SERVER['HTTP_AUTHORIZATION'])
            ]
        ]);
        exit();
    }

    try {
        $decoded = json_decode(base64_decode($token));
        
        if (!$decoded) {
            throw new Exception("Falha ao decodificar token");
        }
        
        if (!isset($decoded->id)) {
            throw new Exception("Token não contém ID do usuário");
        }
        
        if (!isset($decoded->exp)) {
            throw new Exception("Token não contém data de expiração");
        }
        
        if ($decoded->exp < time()) {
            throw new Exception("Token expirado");
        }
        
        $user_id = $decoded->id;
        error_log("User ID autenticado: " . $user_id);
        
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token inválido ou expirado",
            "error" => $e->getMessage()
        ]);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }

    $device = new Device($db);

    $sala = isset($_GET['sala']) ? $_GET['sala'] : null;
    
    error_log("Listando dispositivos - Sala: " . ($sala ? $sala : "TODAS"));

    if ($sala) {
        $stmt = $device->getByRoom($user_id, $sala);
    } else {
        $stmt = $device->getByUser($user_id);
    }

    $num = $stmt->rowCount();
    error_log("Dispositivos encontrados: " . $num);

    if ($num > 0) {
        $devices_arr = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $device_item = array(
                "id" => (int)$row['id'],
                "nome" => $row['nome'],
                "ip" => $row['ip'],
                "tipo" => $row['tipo'],
                "sala" => $row['sala'],
                "descricao" => $row['descricao'] ?? '',
                "status" => $row['status'] ?? 'offline',
                "ativo" => (bool)$row['ativo'],
                "data_cadastro" => $row['data_cadastro'],
                "ultima_conexao" => $row['ultima_conexao'] ?? null
            );
            
            array_push($devices_arr, $device_item);
        }
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $devices_arr,
            "total" => $num
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => [],
            "total" => 0,
            "message" => "Nenhum dispositivo encontrado"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("ERRO EM LIST.PHP: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>