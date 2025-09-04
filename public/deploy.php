<?php
require __DIR__ . '/../config/bootstrap.php';

use Google\Service\Script;
use App\AppsScriptDeployer;
use App\GoogleClientFactory;
use App\Mailer;

$userId = (int)($_GET['uid'] ?? 0);
if ($userId <= 0) { http_response_code(400); echo "Invalid user"; exit; }

$client = GoogleClientFactory::make([
    Google\Service\Script::SCRIPT_PROJECTS,
    Google\Service\Script::SCRIPT_DEPLOYMENTS,
    "https://www.googleapis.com/auth/drive.file",
    "https://www.googleapis.com/auth/spreadsheets"
]);

// Load templates
$codeGs = file_get_contents(__DIR__ . '/../templates/Code.gs');
$indexHtml = file_get_contents(__DIR__ . '/../templates/index.html');

$deployer = new AppsScriptDeployer($db, $client);

try {
    $result = $deployer->deployForUser($userId, $codeGs, $indexHtml);
    $db->log($userId, 'DEPLOYED', $result);

    // Email user
    $stmt = $db->pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $mail = Mailer::make();
    $mail->addAddress($user['email'], $user['name']);
    $mail->Subject = 'Your Expense Tracker is ready';
    $mail->isHTML(true);
    $mail->Body = sprintf('<p>Hi %s,</p><p>Your personal Expense Tracker is ready.</p><p><a href="%s">%s</a></p><p>Regards,<br>%s</p>',
        htmlspecialchars($user['name']), htmlspecialchars($result['webAppUrl']), htmlspecialchars($result['webAppUrl']), htmlspecialchars(getenv('APP_NAME') ?: 'Expense Tracker'));
    $mail->send();

    echo "<h2>Success!</h2><p>Your web app: <a href='".htmlspecialchars($result['webAppUrl'])."'>".htmlspecialchars($result['webAppUrl'])."</a></p>";
} catch (Throwable $e) {
    $db->log($userId, 'DEPLOY_FAILED', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo "Deployment failed: " . htmlspecialchars($e->getMessage());
}
