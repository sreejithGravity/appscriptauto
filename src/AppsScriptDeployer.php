<?php
namespace App;

use Google\Service\Script;
use Google\Service\Script\Content;
use Google\Service\Script\Project;
use Google\Service\Script\File;
use Google\Service\Script\Version;
use Google\Service\Script\CreateVersionRequest;
use Google\Service\Script\Deployment;
use Google\Service\Script\DeploymentConfig;
use Google\Service\Script\EntryPoint;
use Google\Service\Script\EntryPoint\WebApp;
use Google\Service\Oauth2 as GoogleOauth2;
use Exception;

class AppsScriptDeployer {
    public function __construct(private Database $db, private \Google\Client $client) {}

    public function ensureFreshToken(int $userId): void {
        $row = $this->db->getTokens($userId);
        if (!$row) throw new Exception("No OAuth token for user.");
        $token = [
            'access_token' => json_decode($row['access_token'], true),
            'refresh_token' => $row['refresh_token'],
            'expires_in' => max(0, strtotime($row['expires_at']) - time()),
            'created' => time(),
            'scope' => $row['scope'],
            'token_type' => $row['token_type']
        ];
        $this->client->setAccessToken($token);
        if ($this->client->isAccessTokenExpired()) {
            $refreshed = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            if (isset($refreshed['error'])) {
                throw new Exception('Failed to refresh token: ' . $refreshed['error_description']);
            }
            $token = array_merge($token, $refreshed, ['created' => time()]);
            $this->db->saveTokens($userId, $token);
        }
    }

    public function deployForUser(int $userId, string $codeGs, string $indexHtml): array {
        $this->ensureFreshToken($userId);
        $service = new Script($this->client);

        // 1) Create project
        $project = new Project();
        $project->setTitle('Expense Tracker ' . $userId);
        $create = $service->projects->create($project);
        $scriptId = $create->getScriptId();

        // 2) Push files
        $files = [];

        $f1 = new File();
        $f1->setName("Code");
        $f1->setType("SERVER_JS");
        $f1->setSource($codeGs);
        $files[] = $f1;

        $f2 = new File();
        $f2->setName("index");
        $f2->setType("HTML");
        $f2->setSource($indexHtml);
        $files[] = $f2;

        // Minimal manifest to request web app + scopes
        $manifest = [
            "timeZone" => "Asia/Kolkata",
            "exceptionLogging" => "STACKDRIVER",
            "webapp" => [ "access" => "ANYONE", "executeAs" => "USER_ACCESSING" ],
            "oauthScopes" => [
                "https://www.googleapis.com/auth/script.external_request",
                "https://www.googleapis.com/auth/script.scriptapp",
                "https://www.googleapis.com/auth/drive.file",
                "https://www.googleapis.com/auth/spreadsheets"
            ]
        ];
        $f3 = new File();
        $f3->setName("appsscript");
        $f3->setType("JSON");
        $f3->setSource(json_encode($manifest, JSON_PRETTY_PRINT));
        $files[] = $f3;

        $content = new Content();
        $content->setFiles($files);
        $service->projects->updateContent($scriptId, $content);

        // 3) Create version
        $version = new Version();
        $version->setDescription("Initial version");
        $version = $service->projects_versions->create($scriptId, $version);
        $versionNumber = $version->getVersionNumber();

        // 4) Create deployment (web app)
        $deployment = new Deployment();
        $config = new DeploymentConfig();
        $config->setVersionNumber($versionNumber);

        // Web app entryPoint
        $entry = new EntryPoint();
        $web = new WebApp();
        $web->setEntryPointConfig([
            'access' => 'ANYONE',
            'executeAs' => 'USER_ACCESSING'
        ]);
        $entry->setWebApp($web);
        $config->setEntryPoints([$entry]);
        $deployment->setDeploymentConfig($config);

        $deployment = $service->projects_deployments->create($scriptId, $deployment);
        $deploymentId = $deployment->getDeploymentId();

        // Read back web app URL
        $webAppUrl = null;
        foreach ($deployment->getDeploymentConfig()->getEntryPoints() as $ep) {
            if ($ep->getWebApp()) {
                $webAppUrl = $ep->getWebApp()->getUrl();
            }
        }

        $this->db->saveDeployment($userId, $scriptId, (int)$versionNumber, $deploymentId, $webAppUrl ?? '');
        return [
            'scriptId' => $scriptId,
            'deploymentId' => $deploymentId,
            'version' => $versionNumber,
            'webAppUrl' => $webAppUrl
        ];
    }
}
