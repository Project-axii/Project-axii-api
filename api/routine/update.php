<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function normalizarDiasSemana($dias) {
    $dias_validos = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
    $mapeamento = [
        'domingo' => 'domingo',
        'segunda' => 'segunda',
        'segunda-feira' => 'segunda',
        'terca' => 'terca',
        'terça' => 'terca',
        'terca-feira' => 'terca',
        'terça-feira' => 'terca',
        'quarta' => 'quarta',
        'quarta-feira' => 'quarta',
        'quinta' => 'quinta',
        'quinta-feira' => 'quinta',
        'sexta' => 'sexta',
        'sexta-feira' => 'sexta',
        'sabado' => 'sabado',
        'sábado' => 'sabado',
        'sabado-feira' => 'sabado'
    ];
    
    if (is_array($dias)) {
        $dias_normalizados = [];
        foreach ($dias as $dia) {
            $dia_lower = strtolower(trim($dia));
            if (isset($mapeamento[$dia_lower])) {
                $dias_normalizados[] = $mapeamento[$dia_lower];
            } elseif (in_array($dia_lower, $dias_validos)) {
                $dias_normalizados[] = $dia_lower;
            }
        }
        return array_unique($dias_normalizados);
    }
    
    return $dias;
}

try {
    include_once '../../config/database.php';
    include_once '../../models/routine.php';

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

    $data = json_decode(file_get_contents("php://input"));

    if (!$data || !isset($data->id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID da rotina não fornecido"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (empty($data->nome) || empty($data->horario_ini) || empty($data->horario_fim) || 
        empty($data->dias_semana) || empty($data->acao)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Campos obrigatórios não preenchidos"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $acoes_validas = ['ligar', 'desligar', 'reiniciar', 'custom'];
    if (!in_array($data->acao, $acoes_validas)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Ação inválida. Valores aceitos: " . implode(', ', $acoes_validas)
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $rotina = new Rotina($db);
    
    $rotina->id = $data->id;
    $rotina->id_user = $user_id;
    $rotina->nome = $data->nome;
    $rotina->descricao = $data->descricao ?? '';
    $rotina->horario_ini = $data->horario_ini;
    $rotina->horario_fim = $data->horario_fim;
    
    if (is_array($data->dias_semana)) {
        $dias_normalizados = normalizarDiasSemana($data->dias_semana);
        if (empty($dias_normalizados)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Dias da semana inválidos. Use: domingo, segunda, terca, quarta, quinta, sexta, sabado"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $rotina->dias_semana = implode(',', $dias_normalizados);
    } else {
        $rotina->dias_semana = $data->dias_semana;
    }
    
    $rotina->acao = $data->acao;
    $rotina->id_dispositivo = $data->id_dispositivo ?? null;
    $rotina->id_grupo = $data->id_grupo ?? null;
    $rotina->parametros = isset($data->parametros) ? json_encode($data->parametros) : null;
    $rotina->ativo = $data->ativo ?? true;

    if ($rotina->update()) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Rotina atualizada com sucesso"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(503);
        echo json_encode([
            "success" => false,
            "message" => "Não foi possível atualizar a rotina"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("ERRO EM ROTINAS/UPDATE.PHP: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno do servidor",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>