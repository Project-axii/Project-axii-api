<?php
class RateLimiter {

    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ensureTableExists();
    }

    public function check(string $action, string $identifier, int $maxAttempts, int $windowMinutes): void {
        $this->clearExpired($action, $identifier, $windowMinutes);

        $count = $this->countAttempts($action, $identifier, $windowMinutes);

        if ($count >= $maxAttempts) {
            $retryAfter = $this->getRetryAfterSeconds($action, $identifier, $windowMinutes);
            $this->respond429($maxAttempts, $windowMinutes, $retryAfter);
        }

        $this->registerAttempt($action, $identifier);
    }

    public function reset(string $action, string $identifier): void {
        $stmt = $this->db->prepare("
            DELETE FROM rate_limit_attempts
            WHERE action = :action AND identifier = :identifier
        ");
        $stmt->execute([':action' => $action, ':identifier' => $identifier]);
    }

    private function registerAttempt(string $action, string $identifier): void {
        $stmt = $this->db->prepare("
            INSERT INTO rate_limit_attempts (action, identifier, attempted_at)
            VALUES (:action, :identifier, NOW())
        ");
        $stmt->execute([':action' => $action, ':identifier' => $identifier]);
    }

    private function countAttempts(string $action, string $identifier, int $windowMinutes): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM rate_limit_attempts
            WHERE action      = :action
              AND identifier  = :identifier
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL :window MINUTE)
        ");
        $stmt->execute([
            ':action'     => $action,
            ':identifier' => $identifier,
            ':window'     => $windowMinutes,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    private function clearExpired(string $action, string $identifier, int $windowMinutes): void {
        $stmt = $this->db->prepare("
            DELETE FROM rate_limit_attempts
            WHERE action      = :action
              AND identifier  = :identifier
              AND attempted_at < DATE_SUB(NOW(), INTERVAL :window MINUTE)
        ");
        $stmt->execute([
            ':action'     => $action,
            ':identifier' => $identifier,
            ':window'     => $windowMinutes,
        ]);
    }

    private function getRetryAfterSeconds(string $action, string $identifier, int $windowMinutes): int {
        $stmt = $this->db->prepare("
            SELECT MIN(attempted_at) as oldest
            FROM rate_limit_attempts
            WHERE action      = :action
              AND identifier  = :identifier
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL :window MINUTE)
        ");
        $stmt->execute([
            ':action'     => $action,
            ':identifier' => $identifier,
            ':window'     => $windowMinutes,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($row['oldest'])) return $windowMinutes * 60;

        $oldestTs  = strtotime($row['oldest']);
        $expiresAt = $oldestTs + ($windowMinutes * 60);
        return max(1, $expiresAt - time());
    }

    private function respond429(int $maxAttempts, int $windowMinutes, int $retryAfter): void {
        http_response_code(429);
        header("Retry-After: $retryAfter");
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            "success"     => false,
            "message"     => "Muitas tentativas. Tente novamente em " . ceil($retryAfter / 60) . " minuto(s).",
            "retry_after" => $retryAfter,
            "limit"       => $maxAttempts,
            "window"      => "$windowMinutes min",
        ]);
        exit();
    }

    private function ensureTableExists(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_attempts (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action       VARCHAR(64)  NOT NULL,
                identifier   VARCHAR(128) NOT NULL,
                attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_lookup (action, identifier, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}