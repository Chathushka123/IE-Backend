<?php

namespace Tests\Unit\Services\HttpLogging;

use App\Services\HttpLogging\HttpLogRedactor;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class HttpLogRedactorTest extends TestCase
{
    private function redactor(): HttpLogRedactor
    {
        return new HttpLogRedactor(['password', 'token', 'secret']);
    }

    public function testRedactsKnownSensitiveKeysRecursively()
    {
        $result = $this->redactor()->normalizeAndRedact([
            'email' => 'user@example.com',
            'password' => 'plaintext-secret',
            'nested' => [
                'token' => 'abc.def.ghi',
                'note' => 'keep me',
            ],
        ]);

        $this->assertSame('user@example.com', $result['email']);
        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('[REDACTED]', $result['nested']['token']);
        $this->assertSame('keep me', $result['nested']['note']);
    }

    public function testRedactionIsCaseInsensitive()
    {
        $result = $this->redactor()->normalizeAndRedact(['PASSWORD' => 'x', 'Secret' => 'y']);

        $this->assertSame('[REDACTED]', $result['PASSWORD']);
        $this->assertSame('[REDACTED]', $result['Secret']);
    }

    public function testLeavesNonSensitiveDataUntouched()
    {
        $result = $this->redactor()->normalizeAndRedact(['name' => 'Employee A', 'age' => 30]);

        $this->assertSame(['name' => 'Employee A', 'age' => 30], $result);
    }

    public function testReplacesUploadedFilesWithMetadata()
    {
        $file = UploadedFile::fake()->create('photo.jpg', 120);

        $result = $this->redactor()->normalizeAndRedact(['avatar' => $file]);

        $this->assertTrue($result['avatar']['__file__']);
        $this->assertSame('photo.jpg', $result['avatar']['original_name']);
        $this->assertArrayNotHasKey('tmp_name', $result['avatar']);
    }

    public function testPickHeadersKeepsOnlyTheWhitelistedNamesCaseInsensitively()
    {
        $result = $this->redactor()->pickHeaders(
            [
                'authorization' => ['Bearer secret-token'],
                'Content-Type' => ['application/json'],
                'x-factory-ids' => ['3,5'],
            ],
            ['content-type', 'X-Factory-Ids']
        );

        $this->assertArrayNotHasKey('authorization', $result);
        $this->assertSame('application/json', $result['Content-Type']);
        $this->assertSame('3,5', $result['x-factory-ids']);
    }
}
