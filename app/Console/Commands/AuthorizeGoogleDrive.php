<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Console\Command;

/**
 * One-time interactive OAuth flow to obtain a refresh token for uploading
 * HTTP logs to a real Google account's Drive. Service accounts have no
 * storage quota of their own and cannot write file content into a personal
 * (non-Workspace) Drive folder, even one explicitly shared with them — only
 * OAuth as a real user, or a Workspace Shared Drive, can. This runs a
 * loopback HTTP listener (RFC 8252) to catch the one-time redirect from
 * Google's consent screen.
 */
class AuthorizeGoogleDrive extends Command
{
    protected $signature = 'google-drive:authorize {--port=8091}';

    protected $description = 'Interactively authorize this app to upload to your Google Drive and store a refresh token in .env';

    public function handle(): int
    {
        $clientId = config('filesystems.disks.google.oauth_client_id');
        $clientSecret = config('filesystems.disks.google.oauth_client_secret');

        if (!$clientId || !$clientSecret) {
            $this->error('Set GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET in .env first.');
            return self::FAILURE;
        }

        $port = (int) $this->option('port');
        $redirectUri = "http://localhost:{$port}";

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes([GoogleDrive::DRIVE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $this->info('Open this URL in a browser and sign in with the Google account that owns the target Drive folder:');
        $this->newLine();
        $this->line($client->createAuthUrl());
        $this->newLine();
        $this->info("Waiting for the redirect on {$redirectUri} ...");

        $code = $this->waitForAuthorizationCode($port);
        if ($code === null) {
            $this->error('Did not receive an authorization code (timed out or listener failed).');
            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            $this->error('Token exchange failed: ' . ($token['error_description'] ?? $token['error']));
            return self::FAILURE;
        }

        if (empty($token['refresh_token'])) {
            $this->error(
                'Google did not return a refresh token (it only issues one on first consent). '
                . 'Revoke this app\'s access at https://myaccount.google.com/permissions and re-run this command.'
            );
            return self::FAILURE;
        }

        $this->writeEnvValue('GOOGLE_DRIVE_REFRESH_TOKEN', $token['refresh_token']);
        $this->info('Success — GOOGLE_DRIVE_REFRESH_TOKEN written to .env.');

        return self::SUCCESS;
    }

    private function waitForAuthorizationCode(int $port): ?string
    {
        $server = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        if ($server === false) {
            $this->error("Could not start local listener on port {$port}: {$errstr}");
            return null;
        }

        $connection = @stream_socket_accept($server, 180);
        if ($connection === false) {
            fclose($server);
            return null;
        }

        $request = fread($connection, 8192);
        preg_match('#^GET /\?(\S+) HTTP#', (string) $request, $matches);
        parse_str($matches[1] ?? '', $params);

        $body = isset($params['code'])
            ? '<h2>Authorized — you can close this tab and return to the terminal.</h2>'
            : '<h2>No authorization code received.</h2>';
        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: " . strlen($body)
            . "\r\nConnection: close\r\n\r\n{$body}";
        fwrite($connection, $response);
        fclose($connection);
        fclose($server);

        return $params['code'] ?? null;
    }

    private function writeEnvValue(string $key, string $value): void
    {
        $path = base_path('.env');
        $contents = file_get_contents($path);
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

        $contents = preg_match($pattern, $contents)
            ? preg_replace($pattern, "{$key}={$value}", $contents)
            : rtrim($contents) . "\n{$key}={$value}\n";

        file_put_contents($path, $contents);
    }
}
