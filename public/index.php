<?php
require __DIR__ . '/../config/bootstrap.php';

use App\GoogleClientFactory;
use App\RateLimiter;
use Google\Service\Script;

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    // Rate limit
    $rl = new RateLimiter($db);
    $ok = $rl->hit(null, $_SERVER['REMOTE_ADDR'] ?? '', 'register', (int)env('RATE_LIMIT_WINDOW_SECONDS', 3600), (int)env('RATE_LIMIT_MAX_REQUESTS', 30));
    if (!$ok) {
        http_response_code(429);
        echo "Too many requests. Try later.";
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$name || !$email) {
        http_response_code(422);
        echo "Name and email are required.";
        exit;
    }

    $userId = $db->findOrCreateUser($name, $email, $phone);
    $client = GoogleClientFactory::make([
        Script::SCRIPT_PROJECTS,
        Script::SCRIPT_DEPLOYMENTS,
        "https://www.googleapis.com/auth/drive.file",
        "https://www.googleapis.com/auth/spreadsheets"
    ], env('APP_URL') . "/oauth_callback.php");

    $state = base64_encode(json_encode(['uid' => $userId]));
    $authUrl = $client->createAuthUrl() . "&state=" . urlencode($state);

    header("Location: " . $authUrl);
    exit;
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(env('APP_NAME', 'Expense Tracker Deployer')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <style>
    body{font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#0f172a; color:#fff; margin:0}
    .wrap{max-width:680px;margin:48px auto;padding:0 16px}
    .card{background:#111827;border-radius:16px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,0.4)}
    label{display:block;margin:8px 0 6px;font-weight:600}
    input{width:100%;padding:12px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#fff}
    button{margin-top:12px;background:#6366f1;color:#fff;border:0;padding:12px 16px;border-radius:10px;font-weight:700;cursor:pointer}
    h1{margin-top:0}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Deploy your personal Expense Tracker</h1>
      <p>Fill your details and click <strong>Deploy My Expense Tracker</strong>. You'll be redirected to Google for permissions.</p>
      <form method="post">
        <label>Name</label>
        <input name="name" required>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Phone</label>
        <input name="phone" placeholder="+91-...">
        <button type="submit">Deploy My Expense Tracker</button>
      </form>
    </div>
  </div>
</body>
</html>
