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

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Dados inválidos"
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

    $dados = [
        'titulo' => trim($data->titulo),
        'cor' => isset($data->cor) ? $data->cor : 'blue'
    ];

    $lista_id = $lista->criar($user_id, $dados);

    if ($lista_id) {
        if (isset($data->itens) && is_array($data->itens)) {
            foreach ($data->itens as $texto_item) {
                if (!empty(trim($texto_item))) {
                    $lista->adicionarItem($lista_id, $user_id, trim($texto_item));
                }
            }
        }

        $lista_criada = $lista->obterPorId($lista_id, $user_id);
        $itens = $lista->listarItens($lista_id, $user_id);

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Lista criada com sucesso",
            "data" => [
                'id' => (int)$lista_criada['id'],
                'titulo' => $lista_criada['titulo'],
                'cor' => $lista_criada['cor'],
                'ativo' => (bool)$lista_criada['ativo'],
                'itens' => array_map(function($item) {
                    return [
                        'id' => (int)$item['id'],
                        'texto' => $item['texto'],
                        'concluido' => (bool)$item['concluido'],
                        'ordem' => (int)$item['ordem']
                    ];
                }, $itens),
                'data_criacao' => $lista_criada['data_criacao']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("Erro ao criar lista");
    }

} catch (Exception $e) {
    error_log("ERRO EM CRIAR.PHP: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>