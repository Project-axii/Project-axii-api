<?php
require_once '../../vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';
include_once '../../models/User.php';
include_once '../../models/Log.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../..');
$dotenv->load();


$supabase_url = $_ENV['SUPABASE_URL'];
$supabase_key = $_ENV['SUPABASE_KEY'];
$bucket_name = $_ENV['SUPABASE_BUCKET'] ?: 'profile-photos';


$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$log = new Log($db);

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (empty($token)) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token de autenticação não fornecido"
    ]);
    exit();
}

try {
    $decoded = json_decode(base64_decode($token), true);
    
    if (!isset($decoded['id']) || !isset($decoded['exp'])) {
        throw new Exception("Token inválido");
    }
    
    if ($decoded['exp'] < time()) {
        throw new Exception("Token expirado");
    }
    
    $user_id = $decoded['id'];
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token inválido ou expirado"
    ]);
    exit();
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $error_message = "Erro no upload do arquivo";
    
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
    
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $error_message
    ]);
    exit();
}

$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$file_type = $_FILES['photo']['type'];

if (!in_array($file_type, $allowed_types)) {
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
$stmt = $user->findById();
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
        "message" => "Configurações do Supabase não encontradas"
    ]);
    exit();
}

$file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
$file_name = 'user_' . $user_id . '_' . time() . '.' . $file_extension;

$temp_file = $_FILES['photo']['tmp_name'];
$file_content = file_get_contents($temp_file);

$ch = curl_init();
$url = $supabase_url . "/storage/v1/object/" . $bucket_name . "/" . $file_name;

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => $file_content,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $supabase_key,
        "Content-Type: " . $file_type,
        "x-upsert: true"
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $public_url = $supabase_url . "/storage/v1/object/public/" . $bucket_name . "/" . $file_name;
    
    try {
        $query = "UPDATE usuario SET foto = :foto, data_atualizacao = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':foto', $public_url);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            $ip_origem = $_SERVER['REMOTE_ADDR'];
            $log->create(
                $user_id,
                'ATUALIZAR_FOTO',
                'Foto de perfil atualizada com sucesso',
                $ip_origem,
                1
            );
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Foto atualizada com sucesso",
                "photo_url" => $public_url,
                "user" => [
                    "id" => $userData['id'],
                    "nome" => $userData['nome'],
                    "email" => $userData['email'],
                    "foto" => $public_url,
                    "tipo_usuario" => $userData['tipo_usuario']
                ]
            ]);
        } else {
            throw new Exception("Erro ao atualizar banco de dados");
        }
        
    } catch (Exception $e) {
        $ip_origem = $_SERVER['REMOTE_ADDR'];
        $log->create(
            $user_id,
            'ATUALIZAR_FOTO_ERRO',
            'Erro ao atualizar foto no banco: ' . $e->getMessage(),
            $ip_origem,
            0
        );
        
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Erro ao atualizar foto no banco de dados"
        ]);
    }
    
} else {
    $error_data = json_decode($response, true);
    $error_message = isset($error_data['error']) ? $error_data['error'] : "Erro no upload";
    
    if ($curl_error) {
        $error_message .= " - " . $curl_error;
    }
    
    $ip_origem = $_SERVER['REMOTE_ADDR'];
    $log->create(
        $user_id,
        'UPLOAD_FOTO_ERRO',
        'Erro no upload Supabase (HTTP ' . $http_code . '): ' . $error_message,
        $ip_origem,
        0
    );
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro no upload: " . $error_message,
        "http_code" => $http_code
    ]);
}
?>