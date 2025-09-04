<?php
namespace App;

use DateTime;
use DateInterval;

class RateLimiter {
    public function __construct(private Database $db) {}

    public function hit(?int $userId, string $ip, string $route, int $windowSeconds, int $max): bool {
        $windowStart = (new DateTime())->setTime((int)date('H'), (int)(date('i')), 0);
        $windowStart = (new DateTime())->sub(new DateInterval('PT' . (date('s')) . 'S')); // align to minute start
        $window = $windowStart->format('Y-m-d H:i:00');

        // Upsert-like logic
        $pdo = $this->db->pdo;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id, counter FROM rate_limits WHERE user_id <=> ? AND ip <=> ? AND route = ? AND window_start = ? FOR UPDATE");
            $stmt->execute([$userId, $ip, $route, $window]);
            $row = $stmt->fetch();
            if ($row) {
                if ($row['counter'] >= $max) {
                    $pdo->commit();
                    return false;
                }
                $pdo->prepare("UPDATE rate_limits SET counter = counter + 1 WHERE id = ?")->execute([$row['id']]);
            } else {
                $pdo->prepare("INSERT INTO rate_limits (user_id, ip, route, window_start, counter) VALUES (?, ?, ?, ?, 1)")
                    ->execute([$userId, $ip, $route, $window]);
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
