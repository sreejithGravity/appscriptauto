<?php
require __DIR__ . '/../config/bootstrap.php';

use Google\Service\Script;
use App\GoogleClientFactory;

$state = isset($_GET['state']) ? json_decode(base64_decode($_GET['state']), true) : null;
if (!$state || !isset($state['uid'])) { http_response_code(400); echo "Missing state"; exit; }

$client = GoogleClientFactory::make([
    Google\Service\Script::SCRIPT_PROJECTS,
    Google\Service\Script::SCRIPT_DEPLOYMENTS,
    "https://www.googleapis.com/auth/drive.file",
    "https://www.googleapis.com/auth/spreadsheets"
], env('APP_URL') . "/oauth_callback.php");

if (!isset($_GET['code'])) { http_response_code(400); echo "No code"; exit; }


$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) { http_response_code(400); echo "OAuth error: ".$token['error_description']; exit; }

$db->saveTokens((int)$state['uid'], array_merge($token, ['created'=>time()]));

header("Location: " . env('APP_URL') . "/deploy.php?uid=" . (int)$state['uid']);
exit;
