<?php
namespace App;

use Google\Client;

class GoogleClientFactory {
    public static function make(array $scopes, ?string $redirectUri = null): Client {
        $client = new Client();
        $client->setApplicationName(getenv('APP_NAME') ?: 'Apps Script Deployer');
        $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
        if ($redirectUri) $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes($scopes);
        return $client;
    }
}
