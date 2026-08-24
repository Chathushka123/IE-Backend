<?php

namespace Tests\Unit\Services\HttpLogging;

use App\Services\HttpLogging\HttpLogFileManager;
use Tests\TestCase;

class HttpLogFileManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/http_log_manager_test_' . uniqid();
        config([
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

    private function pathOf($handle): string
    {
        return stream_get_meta_data($handle)['uri'];
    }

    private function manager(?callable $onFileClosed = null): HttpLogFileManager
    {
        return new HttpLogFileManager($onFileClosed);
    }

    public function testPinReusesTheSameFileUntilTheLimitIsReached()
    {
        $manager = $this->manager();

        $handle1 = $manager->pin();
        $path1 = $this->pathOf($handle1);
        $manager->append($handle1, ['type' => 'request']);
        $manager->release($handle1);

        $handle2 = $manager->pin();
        $path2 = $this->pathOf($handle2);
        $manager->release($handle2);

        $this->assertSame($path1, $path2);
    }

    public function testAppendWritesRetrievableJsonLinesInOrder()
    {
        $manager = $this->manager();

        $handle = $manager->pin();
        $path = $this->pathOf($handle);
        $manager->append($handle, ['type' => 'request', 'request_id' => 'abc']);
        $manager->append($handle, ['type' => 'response', 'request_id' => 'abc']);
        $manager->release($handle);

        $lines = array_values(array_filter(explode("\n", file_get_contents($path))));
        $this->assertCount(2, $lines);
        $this->assertSame('request', json_decode($lines[0], true)['type']);
        $this->assertSame('response', json_decode($lines[1], true)['type']);
    }

    public function testRotatesToANewFileOnceTheSizeLimitIsReached()
    {
        config(['http_logging.max_bytes' => 10]);
        $manager = $this->manager();

        $handle1 = $manager->pin();
        $path1 = $this->pathOf($handle1);
        $manager->append($handle1, ['payload' => str_repeat('x', 50)]);
        $manager->release($handle1);

        $handle2 = $manager->pin();
        $path2 = $this->pathOf($handle2);
        $manager->release($handle2);

        $this->assertNotSame($path1, $path2);
    }

    public function testClosesAndReportsASupersededFileOnceItsHandleIsReleased()
    {
        config(['http_logging.max_bytes' => 10]);
        $closedPaths = [];
        $manager = $this->manager(function (string $path) use (&$closedPaths) {
            $closedPaths[] = $path;
        });

        $handle1 = $manager->pin();
        $path1 = $this->pathOf($handle1);
        $manager->append($handle1, ['payload' => str_repeat('x', 50)]);
        $manager->release($handle1);

        // The next pin() call both rotates the pointer AND opportunistically
        // drains superseded files that are no longer locked.
        $handle2 = $manager->pin();
        $manager->release($handle2);

        $this->assertFileDoesNotExist($path1);
        $this->assertCount(1, $manager->pendingLogFiles());
        $this->assertSame(basename($path1), basename($manager->pendingLogFiles()[0]));
        $this->assertCount(1, $closedPaths);
    }

    public function testDoesNotCloseAFileWhoseHandleIsStillHeldByAnInFlightRequest()
    {
        config(['http_logging.max_bytes' => 10]);
        $manager = $this->manager();

        $handle1 = $manager->pin();
        $manager->append($handle1, ['payload' => str_repeat('x', 50)]);
        // Deliberately NOT released yet — simulates a request still mid-flight
        // when the file it pinned crosses the size limit.

        $handle2 = $manager->pin();
        $this->assertNotSame($this->pathOf($handle1), $this->pathOf($handle2));
        $this->assertCount(0, $manager->pendingLogFiles(), 'file must not be closed while still locked');

        $manager->release($handle1);
        $manager->release($handle2);

        // A later pin() opportunistically drains it now that it's unlocked.
        $handle3 = $manager->pin();
        $manager->release($handle3);
        $this->assertCount(1, $manager->pendingLogFiles());
    }

    public function testForceCloseActiveFileMovesAnUnlockedFile()
    {
        $manager = $this->manager();

        $handle = $manager->pin();
        $path = $this->pathOf($handle);
        $manager->append($handle, ['type' => 'request']);
        $manager->release($handle);

        $closed = $manager->forceCloseActiveFile();

        $this->assertNotNull($closed);
        $this->assertFileDoesNotExist($path);
        $this->assertFileExists($closed);
    }

    public function testForceCloseActiveFileLeavesAStillLockedFileAlone()
    {
        $manager = $this->manager();

        $handle = $manager->pin();
        $path = $this->pathOf($handle);
        $manager->append($handle, ['type' => 'request']);
        // Not released — still "in-flight".

        $closed = $manager->forceCloseActiveFile();

        $this->assertNull($closed);
        $this->assertFileExists($path);

        $manager->release($handle);
    }
}
