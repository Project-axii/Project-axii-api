<?php
require_once '../../vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';
include_once '../../models/User.php';
include_once '../../models/Log.php';
include_once '../middleware/rate_limiter.php';
include_once '../middleware/auth.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../..');
$dotenv->load();

$supabase_url = $_ENV['SUPABASE_URL'] ?? null;
$supabase_key = $_ENV['SUPABASE_KEY'] ?? null;
$bucket_name  = $_ENV['SUPABASE_BUCKET'] ?? 'profile-photos';

$database = new Database();
$db = $database->getConnection();

$limiter   = new RateLimiter($db);
$ip_origem = $_SERVER['REMOTE_ADDR'];

$limiter->check('update_photo', $ip_origem, 5, 10);

$user = new User($db);
$log  = new Log($db);

// Verificação de autenticação obrigatória
$authUser = requireAuth();
$user_id  = $authUser['id'];

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $error_message = "Erro no upload do arquivo";

    if (isset($_FILES['photo']['error'])) {
        switch ($_FILES['photo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = "O arquivo excede o tamanho máximo permitido";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = "O upload foi interrompido";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = "Nenhum arquivo foi enviado";
                break;
        }
    }

    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $error_message
    ]);
    exit();
}

// Verificação real do tipo de arquivo via magic bytes (finfo), não via header do cliente
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$real_mime_type = finfo_file($finfo, $_FILES['photo']['tmp_name']);
finfo_close($finfo);

if (!in_array($real_mime_type, $allowed_mime_types)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Tipo de arquivo não permitido. Use apenas JPG, PNG ou WEBP"
    ]);
    exit();
}

$max_size = 5 * 1024 * 1024;
if ($_FILES['photo']['size'] > $max_size) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "O arquivo é muito grande. O tamanho máximo é 5MB"
    ]);
    exit();
}

$user->id = $user_id;
$stmt     = $user->findById();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Usuário não encontrado"
    ]);
    exit();
}

if (!$supabase_url || !$supabase_key) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Configurações do serviço de armazenamento não encontradas"
    ]);
    exit();
}

// Gerar nome de arquivo seguro sem usar o nome original enviado pelo cliente
$extension_map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$file_extension = $extension_map[$real_mime_type];
$file_name      = 'user_' . $user_id . '_' . time() . '.' . $file_extension;

$temp_file   = $_FILES['photo']['tmp_name'];
$file_content = file_get_contents($temp_file);

$ch = curl_init();
$url = $supabase_url . "/storage/v1/object/" . $bucket_name . "/" . $file_name;

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => "POST",
    CURLOPT_POSTFIELDS     => $file_content,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer " . $supabase_key,
        "Content-Type: " . $real_mime_type,
        "x-upsert: true"
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $public_url = $supabase_url . "/storage/v1/object/public/" . $bucket_name . "/" . $file_name;

    try {
        $query = "UPDATE usuario SET foto = :foto, data_atualizacao = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt  = $db->prepare($query);

        $stmt->bindParam(':foto', $public_url);
        $stmt->bindParam(':id', $user_id);

        if ($stmt->execute()) {
            $log->create(
                $user_id,
                'ATUALIZAR_FOTO',
                'Foto de perfil atualizada com sucesso',
                $ip_origem,
                1
            );

            http_response_code(200);
            echo json_encode([
                "success"   => true,
                "message"   => "Foto atualizada com sucesso",
                "photo_url" => $public_url,
                "user"      => [
                    "id"           => $userData['id'],
                    "nome"         => $userData['nome'],
                    "email"        => $userData['email'],
                    "foto"         => $public_url,
                    "tipo_usuario" => $userData['tipo_usuario']
                ]
            ]);
        } else {
            throw new Exception("Erro ao atualizar banco de dados");
        }

    } catch (Exception $e) {
        $log->create(
            $user_id,
            'ATUALIZAR_FOTO_ERRO',
            'Erro ao atualizar foto no banco',
            $ip_origem,
            0
        );

        error_log("Erro em update_photo.php (DB): " . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Erro ao atualizar foto no banco de dados"
        ]);
    }

} else {
    $log->create(
        $user_id,
        'UPLOAD_FOTO_ERRO',
        'Erro no upload de foto (HTTP ' . $http_code . ')',
        $ip_origem,
        0
    );

    error_log("Erro upload Supabase: HTTP $http_code" . ($curl_error ? " | $curl_error" : ""));

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro no upload da foto. Tente novamente."
    ]);
}
?>
