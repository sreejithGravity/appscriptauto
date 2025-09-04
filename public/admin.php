<?php
require __DIR__ . '/../config/bootstrap.php';

if (($_GET['key'] ?? '') !== env('ADMIN_API_KEY')) { http_response_code(401); echo "Unauthorized"; exit; }

$deployments = $db->pdo->query("SELECT d.*, u.name, u.email FROM deployments d JOIN users u ON u.id=d.user_id ORDER BY d.created_at DESC LIMIT 200")->fetchAll();
$logs = $db->pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 200")->fetchAll();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Admin</title>
<style>body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px}
table{border-collapse:collapse;width:100%}th,td{border:1px solid #eee;padding:8px}th{background:#f8fafc;text-align:left}
h2{margin-top:32px}
</style></head>
<body>
<h1>Admin Dashboard</h1>
<h2>Deployments</h2>
<table>
<tr><th>User</th><th>Email</th><th>Script ID</th><th>Version</th><th>Deployment ID</th><th>URL</th><th>Status</th><th>Created</th></tr>
<?php foreach($deployments as $d): ?>
<tr>
<td><?= htmlspecialchars($d['name']) ?></td>
<td><?= htmlspecialchars($d['email']) ?></td>
<td><?= htmlspecialchars($d['script_id']) ?></td>
<td><?= (int)$d['version_number'] ?></td>
<td><?= htmlspecialchars($d['deployment_id']) ?></td>
<td><a href="<?= htmlspecialchars($d['web_app_url']) ?>" target="_blank">Open</a></td>
<td><?= htmlspecialchars($d['status']) ?></td>
<td><?= htmlspecialchars($d['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Recent Logs</h2>
<table>
<tr><th>When</th><th>User ID</th><th>Event</th><th>Context</th></tr>
<?php foreach($logs as $l): ?>
<tr>
<td><?= htmlspecialchars($l['created_at']) ?></td>
<td><?= htmlspecialchars((string)$l['user_id']) ?></td>
<td><?= htmlspecialchars($l['event']) ?></td>
<td><pre style="white-space:pre-wrap"><?= htmlspecialchars($l['context']) ?></pre></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
