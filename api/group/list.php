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
    include_once '../middleware/auth.php';

    $authUser = requireAuth();
    $user_id  = $authUser['id'];

    $database = new Database();
    $db       = $database->getConnection();

    $query = "SELECT
                g.id,
                g.nome,
                g.descricao,
                g.cor,
                g.ativo,
                g.data_criacao,
                COUNT(gd.id_dispositivo) as total_dispositivos
              FROM grupo g
              LEFT JOIN grupo_dispositivo gd ON g.id = gd.id_grupo
              WHERE g.id_user = :user_id AND g.ativo = 1
              GROUP BY g.id, g.nome, g.descricao, g.cor, g.ativo, g.data_criacao
              ORDER BY g.nome";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $grupos_arr = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $grupos_arr[] = [
            "id"                 => (int)$row['id'],
            "nome"               => $row['nome'],
            "descricao"          => $row['descricao'],
            "cor"                => $row['cor'],
            "ativo"              => (bool)$row['ativo'],
            "data_criacao"       => $row['data_criacao'],
            "total_dispositivos" => (int)$row['total_dispositivos']
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data"    => $grupos_arr,
        "total"   => count($grupos_arr)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("ERRO EM list.php (group): " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor"
    ], JSON_UNESCAPED_UNICODE);
}
?>
