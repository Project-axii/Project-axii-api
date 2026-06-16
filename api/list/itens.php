<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
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
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));

        if (!$data || !isset($data->id_lista) || empty($data->texto)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ID da lista e texto do item são obrigatórios"
            ]);
            exit();
        }

        $item_id = $lista->adicionarItem($data->id_lista, $user_id, trim($data->texto));

        if ($item_id) {
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Item adicionado com sucesso",
                "data"    => ["id" => $item_id]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception("Erro ao adicionar item");
        }

    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents("php://input"));

        if (!$data || !isset($data->id) || !isset($data->id_lista)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ID do item e ID da lista são obrigatórios"
            ]);
            exit();
        }

        $dados_item = [
            'texto'     => isset($data->texto) ? trim($data->texto) : '',
            'concluido' => isset($data->concluido) ? $data->concluido : false
        ];

        if ($lista->atualizarItem($data->id, $data->id_lista, $user_id, $dados_item)) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Item atualizado com sucesso"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception("Erro ao atualizar item");
        }

    } elseif ($method === 'DELETE') {
        $id       = isset($_GET['id'])       ? (int)$_GET['id']       : 0;
        $id_lista = isset($_GET['id_lista']) ? (int)$_GET['id_lista'] : 0;

        if ($id <= 0 || $id_lista <= 0) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ID do item e ID da lista são obrigatórios"
            ]);
            exit();
        }

        if ($lista->deletarItem($id, $id_lista, $user_id)) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Item deletado com sucesso"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception("Erro ao deletar item");
        }

    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método não permitido"]);
    }

} catch (Exception $e) {
    error_log("ERRO EM itens.php (list): " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>
