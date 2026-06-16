<?php
/**
 * Middleware de autenticação centralizado.
 * Verifica o token Bearer enviado no header Authorization.
 * Retorna array com dados do usuário autenticado ou encerra a resposta com 401.
 */
function requireAuth(): array {
    $token = null;

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
    }

    if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }

    if (!$token && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (empty($token)) {
        http_response_code(401);
        echo json_encode([
            "success"  => false,
            "message"  => "Token de autenticação não fornecido"
        ]);
        exit();
    }

    try {
        $decoded = json_decode(base64_decode($token), true);

        if (!is_array($decoded) || !isset($decoded['id']) || !isset($decoded['exp'])) {
            throw new Exception("Estrutura do token inválida");
        }

        if ((int)$decoded['exp'] < time()) {
            throw new Exception("Token expirado");
        }

        if (!is_int($decoded['id']) && !ctype_digit((string)$decoded['id'])) {
            throw new Exception("ID de usuário inválido no token");
        }

        return [
            'id'    => (int)$decoded['id'],
            'email' => $decoded['email'] ?? null,
        ];

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token inválido ou expirado"
        ]);
        exit();
    }
}

/**
 * Adiciona headers de segurança HTTP padrão.
 * Deve ser chamado antes de qualquer saída.
 */
function addSecurityHeaders(): void {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Cache-Control: no-store, no-cache, must-revalidate");
}
