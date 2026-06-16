<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
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

    $data = json_decode(file_get_contents("php://input"));

    if (!$data || !isset($data->id)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID da lista é obrigatório"]);
        exit();
    }

    if (empty($data->titulo)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Título é obrigatório"]);
        exit();
    }

    $database = new Database();
    $db       = $database->getConnection();

    $lista           = new Lista($db);
    $lista_existente = $lista->obterPorId($data->id, $user_id);

    if (!$lista_existente) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Lista não encontrada"]);
        exit();
    }

    $dados = [
        'titulo' => trim($data->titulo),
        'cor'    => isset($data->cor) ? $data->cor : $lista_existente['cor']
    ];

    if ($lista->atualizar($data->id, $user_id, $dados)) {
        $lista_atualizada = $lista->obterPorId($data->id, $user_id);
        $itens            = $lista->listarItens($data->id, $user_id);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Lista atualizada com sucesso",
            "data"    => [
                'id'           => (int)$lista_atualizada['id'],
                'titulo'       => $lista_atualizada['titulo'],
                'cor'          => $lista_atualizada['cor'],
                'ativo'        => (bool)$lista_atualizada['ativo'],
                'itens'        => array_map(function ($item) {
                    return [
                        'id'        => (int)$item['id'],
                        'texto'     => $item['texto'],
                        'concluido' => (bool)$item['concluido'],
                        'ordem'     => (int)$item['ordem']
                    ];
                }, $itens),
                'data_criacao' => $lista_atualizada['data_criacao']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("Erro ao atualizar lista");
    }

} catch (Exception $e) {
    error_log("ERRO EM update.php (list): " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>
