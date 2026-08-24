<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Proves LogHttpTraffic actually logs a real request through the full HTTP
 * kernel/middleware stack, correlates the request/response pair via
 * request_id, redacts sensitive fields (password) in the logged body, keeps
 * headers to the audit whitelist, and attributes entries to the requesting
 * user once auth middleware (further down the stack) has resolved it.
 */
class HttpLoggingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/http_logging_feature_test_' . uniqid();
        config([
            'http_logging.enabled' => true,
            'http_logging.active_dir' => $this->root . '/active',
            'http_logging.pending_upload_dir' => $this->root . '/pending_upload',
            'http_logging.pointer_file' => $this->root . '/.pointer',
            'http_logging.max_bytes' => 1024 * 1024,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->root);
        parent::tearDown();
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** @return array[] */
    private function readLoggedEntries(): array
    {
        $files = glob($this->root . '/active/*.log') ?: [];
        $this->assertNotEmpty($files, 'expected an active http log file to exist');

        $lines = array_values(array_filter(explode("\n", file_get_contents($files[0]))));
        return array_map(fn ($line) => json_decode($line, true), $lines);
    }

    public function testLogsARequestResponsePairWithThePasswordRedacted()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'super-secret-pw',
        ]);

        $response->assertHeader('X-Request-Id');

        $entries = $this->readLoggedEntries();
        $this->assertCount(2, $entries);
        [$requestEntry, $responseEntry] = $entries;

        $this->assertSame('request', $requestEntry['type']);
        $this->assertSame('POST', $requestEntry['method']);
        $this->assertSame('[REDACTED]', $requestEntry['body']['password']);
        $this->assertSame('nobody@example.com', $requestEntry['body']['email']);
        $this->assertSame($response->headers->get('X-Request-Id'), $requestEntry['request_id']);
        $this->assertNull($requestEntry['user_email'], 'no authenticated user for a failed login');

        $this->assertArrayHasKey('content-type', $requestEntry['headers']);
        $this->assertArrayNotHasKey('authorization', $requestEntry['headers']);
        $this->assertArrayNotHasKey('cookie', $requestEntry['headers']);

        $this->assertSame('response', $responseEntry['type']);
        $this->assertSame($response->getStatusCode(), $responseEntry['status']);
        $this->assertSame($requestEntry['request_id'], $responseEntry['request_id']);
    }

    public function testAttributesEntriesToTheAuthenticatedUserOnceResolved()
    {
        $email = 'audit-tester-' . uniqid() . '@example.com';
        $userId = DB::table('users')->insertGetId([
            'name' => 'Audit Tester',
            'email' => $email,
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = JWTAuth::fromUser(User::find($userId));

        $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$token}"]);

        [$requestEntry, $responseEntry] = $this->readLoggedEntries();

        $this->assertSame($userId, $requestEntry['user_id']);
        $this->assertSame($email, $requestEntry['user_email']);
        $this->assertSame($userId, $responseEntry['user_id']);
        $this->assertSame($email, $responseEntry['user_email']);
    }
}
