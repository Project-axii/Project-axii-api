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
    include_once '../../models/routine.php';

    $token = null;
    
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
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
        ], JSON_UNESCAPED_UNICODE);
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
            "message" => "Token inválido ou expirado"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }

    $rotina = new Rotina($db);
    $stmt = $rotina->getByUser($user_id);
    $num = $stmt->rowCount();

    if ($num > 0) {
        $rotinas_arr = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dias_array = explode(',', $row['dias_semana']);
            
            $rotina_item = array(
                "id" => (int)$row['id'],
                "nome" => $row['nome'],
                "descricao" => $row['descricao'] ?? '',
                "horario_ini" => substr($row['horario_ini'], 0, 5),
                "horario_fim" => substr($row['horario_fim'], 0, 5),
                "dias_semana" => $dias_array,
                "acao" => $row['acao'],
                "parametros" => $row['parametros'] ? json_decode($row['parametros']) : null,
                "ativo" => (bool)$row['ativo'],
                "alvo_nome" => $row['alvo_nome'] ?? null,
                "alvo_tipo" => $row['alvo_tipo'] ?? null,
                "id_dispositivo" => $row['id_dispositivo'] ? (int)$row['id_dispositivo'] : null,
                "id_grupo" => $row['id_grupo'] ? (int)$row['id_grupo'] : null,
                "data_criacao" => $row['data_criacao']
            );
            
            array_push($rotinas_arr, $rotina_item);
        }
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $rotinas_arr,
            "total" => $num
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => [],
            "total" => 0,
            "message" => "Nenhuma rotina encontrada"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("ERRO EM ROTINAS/LIST.PHP: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>