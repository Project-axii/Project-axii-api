<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Senha é obrigatória"
    ]);
    exit();
}

$password = $data->password;
$strength = [
    "score" => 0,
    "feedback" => [],
    "level" => "weak"
];

if (strlen($password) >= 8) {
    $strength["score"] += 25;
    $strength["feedback"][] = "Comprimento adequado";
} else {
    $strength["feedback"][] = "Use pelo menos 8 caracteres";
}

if (preg_match('/[A-Z]/', $password)) {
    $strength["score"] += 25;
    $strength["feedback"][] = "Contém letras maiúsculas";
} else {
    $strength["feedback"][] = "Adicione letras maiúsculas";
}

if (preg_match('/[a-z]/', $password)) {
    $strength["score"] += 25;
    $strength["feedback"][] = "Contém letras minúsculas";
} else {
    $strength["feedback"][] = "Adicione letras minúsculas";
}

if (preg_match('/[0-9]/', $password)) {
    $strength["score"] += 15;
    $strength["feedback"][] = "Contém números";
} else {
    $strength["feedback"][] = "Adicione números";
}

if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    $strength["score"] += 10;
    $strength["feedback"][] = "Contém caracteres especiais";
} else {
    $strength["feedback"][] = "Adicione caracteres especiais";
}

if ($strength["score"] >= 80) {
    $strength["level"] = "strong";
} elseif ($strength["score"] >= 50) {
    $strength["level"] = "medium";
} else {
    $strength["level"] = "weak";
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "strength" => $strength
]);
?>