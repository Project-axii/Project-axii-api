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

    $data = json_decode(file_get_contents("php://input"));

    if (!$data || !isset($data->id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID da lista é obrigatório"
        ]);
        exit();
    }

    if (empty($data->titulo)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Título é obrigatório"
        ]);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }

    $lista = new Lista($db);

    $lista_existente = $lista->obterPorId($data->id, $user_id);
    if (!$lista_existente) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Lista não encontrada"
        ]);
        exit();
    }

    $dados = [
        'titulo' => trim($data->titulo),
        'cor' => isset($data->cor) ? $data->cor : $lista_existente['cor']
    ];

    if ($lista->atualizar($data->id, $user_id, $dados)) {
        $lista_atualizada = $lista->obterPorId($data->id, $user_id);
        $itens = $lista->listarItens($data->id, $user_id);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Lista atualizada com sucesso",
            "data" => [
                'id' => (int)$lista_atualizada['id'],
                'titulo' => $lista_atualizada['titulo'],
                'cor' => $lista_atualizada['cor'],
                'ativo' => (bool)$lista_atualizada['ativo'],
                'itens' => array_map(function($item) {
                    return [
                        'id' => (int)$item['id'],
                        'texto' => $item['texto'],
                        'concluido' => (bool)$item['concluido'],
                        'ordem' => (int)$item['ordem']
                    ];
                }, $itens),
                'data_criacao' => $lista_atualizada['data_criacao']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("Erro ao atualizar lista");
    }

} catch (Exception $e) {
    error_log("ERRO EM ATUALIZAR.PHP: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>