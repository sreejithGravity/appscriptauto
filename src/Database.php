<?php
namespace App;

use PDO;
use PDOException;

class Database {
    public PDO $pdo;

    public function __construct(string $dsn, string $user, string $pass) {
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function findOrCreateUser(string $name, string $email, ?string $phone = null): int {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, phone) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $phone]);
        return (int)$this->pdo->lastInsertId();
    }

    public function saveTokens(int $userId, array $token): void {
        $stmt = $this->pdo->prepare("SELECT id FROM oauth_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $exists = $stmt->fetchColumn();

        $expiresAt = date('Y-m-d H:i:s', $token['created'] + $token['expires_in']);
        $params = [
            $userId,
            json_encode($token['access_token']),
            $token['refresh_token'] ?? '',
            $expiresAt,
            $token['scope'] ?? '',
            $token['token_type'] ?? 'Bearer'
        ];

        if ($exists) {
            $sql = "UPDATE oauth_tokens SET access_token=?, refresh_token=?, expires_at=?, scope=?, token_type=?, updated_at=NOW() WHERE user_id=?";
            $this->pdo->prepare($sql)->execute([
                $params[1], $params[2], $params[3], $params[4], $params[5], $userId
            ]);
        } else {
            $sql = "INSERT INTO oauth_tokens (user_id, access_token, refresh_token, expires_at, scope, token_type) VALUES (?, ?, ?, ?, ?, ?)";
            $this->pdo->prepare($sql)->execute($params);
        }
    }

    public function getTokens(int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM oauth_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveDeployment(int $userId, string $scriptId, int $version, string $deploymentId, string $webAppUrl, string $status='DEPLOYED', ?string $message=null): int {
        $sql = "INSERT INTO deployments (user_id, script_id, version_number, deployment_id, web_app_url, status, message) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $this->pdo->prepare($sql)->execute([$userId, $scriptId, $version, $deploymentId, $webAppUrl, $status, $message]);
        return (int)$this->pdo->lastInsertId();
    }

    public function log(?int $userId, string $event, array $context = []): void {
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, event, context) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $event, json_encode($context)]);
    }
}
