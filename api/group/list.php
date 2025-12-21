<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include_once '../../config/database.php';
    
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
        ], JSON_UNESCAPED_UNICODE);
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
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }
    
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
    
    $grupos_arr = array();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $grupo_item = array(
            "id" => (int)$row['id'],
            "nome" => $row['nome'],
            "descricao" => $row['descricao'],
            "cor" => $row['cor'],
            "ativo" => (bool)$row['ativo'],
            "data_criacao" => $row['data_criacao'],
            "total_dispositivos" => (int)$row['total_dispositivos']
        );
        
        array_push($grupos_arr, $grupo_item);
    }
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $grupos_arr,
        "total" => count($grupos_arr)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("ERRO EM GRUPOS/LIST.PHP: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>