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
    include_once '../../models/Device.php';
    include_once '../middleware/auth.php';

    $authUser = requireAuth();
    $user_id  = $authUser['id'];

    $database = new Database();
    $db       = $database->getConnection();

    $device = new Device($db);

    $sala = isset($_GET['sala']) ? $_GET['sala'] : null;

    if ($sala) {
        $stmt = $device->getByRoom($user_id, $sala);
    } else {
        $stmt = $device->getByUser($user_id);
    }

    $num = $stmt->rowCount();

    if ($num > 0) {
        $devices_arr = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $devices_arr[] = [
                "id"             => (int)$row['id'],
                "nome"           => $row['nome'],
                "ip"             => $row['ip'],
                "tipo"           => $row['tipo'],
                "sala"           => $row['sala'],
                "descricao"      => $row['descricao'] ?? '',
                "status"         => $row['status'] ?? 'offline',
                "ativo"          => (bool)$row['ativo'],
                "data_cadastro"  => $row['data_cadastro'],
                "ultima_conexao" => $row['ultima_conexao'] ?? null
            ];
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data"    => $devices_arr,
            "total"   => $num
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data"    => [],
            "total"   => 0,
            "message" => "Nenhum dispositivo encontrado"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("ERRO EM list.php (devices): " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>