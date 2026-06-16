<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';
include_once '../../models/Log.php';
include_once '../middleware/rate_limiter.php';
include_once '../middleware/auth.php';

$database = new Database();
$db = $database->getConnection();

$limiter   = new RateLimiter($db);
$ip_origem = $_SERVER['REMOTE_ADDR'];

$limiter->check('Logout', $ip_origem, 5, 10);

$log = new Log($db);

// Verificação de autenticação obrigatória — user_id vem do token, não do body
$authUser = requireAuth();
$userId   = $authUser['id'];

$log->create(
    $userId,
    'LOGOUT',
    'Usuário saiu do sistema',
    $ip_origem,
    1
);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Logout realizado com sucesso"
]);
?>
