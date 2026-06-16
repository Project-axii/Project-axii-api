<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include_once '../../config/database.php';
    include_once '../../models/list.php';
    include_once '../middleware/auth.php';

    $authUser = requireAuth();
    $user_id  = $authUser['id'];

    $database = new Database();
    $db       = $database->getConnection();

    $lista  = new Lista($db);
    $listas = $lista->listar($user_id);

    $resultado = [];
    foreach ($listas as $l) {
        $itens = $lista->listarItens($l['id'], $user_id);

        $resultado[] = [
            'id'         => (int)$l['id'],
            'titulo'     => $l['titulo'],
            'cor'        => $l['cor'],
            'ativo'      => (bool)$l['ativo'],
            'total_itens'=> (int)$l['total_itens'],
            'concluidos' => (int)$l['itens_concluidos'],
            'itens'      => array_map(function ($item) {
                return [
                    'id'             => (int)$item['id'],
                    'texto'          => $item['texto'],
                    'concluido'      => (bool)$item['concluido'],
                    'ordem'          => (int)$item['ordem'],
                    'data_criacao'   => $item['data_criacao'],
                    'data_conclusao' => $item['data_conclusao']
                ];
            }, $itens),
            'data_criacao' => $l['data_criacao']
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data"    => $resultado
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("ERRO EM list.php (list): " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>
