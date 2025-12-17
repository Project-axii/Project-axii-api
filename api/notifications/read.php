<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
              (isset($headers['authorization']) ? $headers['authorization'] : null);

if (!$authHeader) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token não fornecido"
    ]);
    exit();
}

$token = str_replace('Bearer ', '', $authHeader);

try {
    $tokenParts = explode('.', $token);
    
    if (count($tokenParts) < 2) {
        $tokenData = json_decode(base64_decode($token), true);
    } else {
        $tokenData = json_decode(base64_decode($tokenParts[1]), true);
    }
    
    if (!isset($tokenData['id'])) {
        throw new Exception('ID não encontrado no token');
    }
    
    $userId = $tokenData['id'];
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token inválido: " . $e->getMessage()
    ]);
    exit();
}

$query = "SELECT 
            n.id,
            n.tipo,
            n.titulo,
            n.mensagem,
            n.lida,
            n.data_criacao,
            n.data_leitura,
            d.nome as dispositivo_nome,
            d.tipo as dispositivo_tipo
          FROM notificacoes n
          LEFT JOIN dispositivos d ON n.id_dispositivo = d.id
          WHERE n.id_user = :user_id
          ORDER BY n.data_criacao DESC
          LIMIT 50";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();

$notificacoes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dataCriacao = new DateTime($row['data_criacao']);
    $agora = new DateTime();
    $diff = $agora->diff($dataCriacao);
    
    if ($diff->d > 0) {
        $tempoDecorrido = $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
    } elseif ($diff->h > 0) {
        $tempoDecorrido = $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
    } elseif ($diff->i > 0) {
        $tempoDecorrido = $diff->i . ' min atrás';
    } else {
        $tempoDecorrido = 'Agora';
    }
    
    $iconData = [
        'info' => ['icon' => 'info', 'color' => '#3B82F6'],
        'aviso' => ['icon' => 'warning', 'color' => '#F59E0B'],
        'erro' => ['icon' => 'error', 'color' => '#EF4444'],
        'sucesso' => ['icon' => 'check_circle', 'color' => '#10B981']
    ];
    
    $tipo = $row['tipo'];
    $icon = $iconData[$tipo]['icon'] ?? 'notifications';
    $color = $iconData[$tipo]['color'] ?? '#8B5CF6';
    
    $notificacoes[] = [
        'id' => (int)$row['id'],
        'tipo' => $tipo,
        'titulo' => $row['titulo'],
        'mensagem' => $row['mensagem'],
        'lida' => (bool)$row['lida'],
        'tempo' => $tempoDecorrido,
        'data_criacao' => $row['data_criacao'],
        'data_leitura' => $row['data_leitura'],
        'dispositivo' => $row['dispositivo_nome'],
        'dispositivo_tipo' => $row['dispositivo_tipo'],
        'icon' => $icon,
        'color' => $color
    ];
}

$queryNaoLidas = "SELECT COUNT(*) as total FROM notificacoes WHERE id_user = :user_id AND lida = 0";
$stmtNaoLidas = $db->prepare($queryNaoLidas);
$stmtNaoLidas->bindParam(':user_id', $userId);
$stmtNaoLidas->execute();
$naoLidas = $stmtNaoLidas->fetch(PDO::FETCH_ASSOC)['total'];

http_response_code(200);
echo json_encode([
    "success" => true,
    "data" => $notificacoes,
    "nao_lidas" => (int)$naoLidas,
    "total" => count($notificacoes)
]);
?>