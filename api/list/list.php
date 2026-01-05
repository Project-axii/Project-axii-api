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
    include_once '../../models/list.php';

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
            "message" => "Token inválido ou expirado"
        ]);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }

    $lista = new Lista($db);
    $listas = $lista->listar($user_id);

    $resultado = [];
    foreach ($listas as $l) {
        $itens = $lista->listarItens($l['id'], $user_id);
        
        $resultado[] = [
            'id' => (int)$l['id'],
            'titulo' => $l['titulo'],
            'cor' => $l['cor'],
            'ativo' => (bool)$l['ativo'],
            'total_itens' => (int)$l['total_itens'],
            'concluidos' => (int)$l['itens_concluidos'],
            'itens' => array_map(function($item) {
                return [
                    'id' => (int)$item['id'],
                    'texto' => $item['texto'],
                    'concluido' => (bool)$item['concluido'],
                    'ordem' => (int)$item['ordem'],
                    'data_criacao' => $item['data_criacao'],
                    'data_conclusao' => $item['data_conclusao']
                ];
            }, $itens),
            'data_criacao' => $l['data_criacao']
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $resultado
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("ERRO EM LISTAR.PHP: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>