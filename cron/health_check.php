<?php
require __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

// Simple DB ping + last log time
$ok = true;
try {
    $db->pdo->query("SELECT 1");
} catch (Throwable $e) {
    $ok = false;
}
$lastLog = $db->pdo->query("SELECT MAX(created_at) AS last_log FROM audit_logs")->fetchColumn();

echo json_encode([
    'ok' => $ok,
    'time' => gmdate('c'),
    'last_log' => $lastLog,
]);
