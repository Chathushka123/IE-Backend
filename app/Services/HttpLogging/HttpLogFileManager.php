<?php

namespace App\Services\HttpLogging;

use App\Jobs\ZipAndUploadHttpLogFile;
use Illuminate\Support\Str;

/**
 * Owns the "current active log file" pointer and rotates it once it reaches
 * the configured size limit.
 *
 * Rotating the pointer (so new requests get a new file) is decoupled from
 * actually closing/uploading the superseded file: pin() returns an open file
 * handle with a shared (LOCK_SH) advisory lock held for the caller's whole
 * request lifetime. A file is only ever moved out for upload once nobody
 * still holds that shared lock (verified via a non-blocking exclusive-lock
 * probe) — otherwise a request whose write straddles a rotation would have
 * its response entry silently lost. This is what guarantees a single
 * request's request/response pair always lands in the same file, and that a
 * file is only allowed to exceed the size limit for requests already
 * in-flight when it crossed the threshold.
 */
class HttpLogFileManager
{
    private string $activeDir;
    private string $pendingUploadDir;
    private string $pointerFile;
    private int $maxBytes;

    /** @var callable(string):void */
    private $onFileClosed;

    public function __construct(?callable $onFileClosed = null)
    {
        $this->activeDir = config('http_logging.active_dir');
        $this->pendingUploadDir = config('http_logging.pending_upload_dir');
        $this->pointerFile = config('http_logging.pointer_file');
        $this->maxBytes = config('http_logging.max_bytes');
        $this->onFileClosed = $onFileClosed ?? function (string $path): void {
            dispatch(new ZipAndUploadHttpLogFile($path))->afterResponse();
        };

        if (!is_dir($this->activeDir)) {
            mkdir($this->activeDir, 0775, true);
        }
        if (!is_dir($this->pendingUploadDir)) {
            mkdir($this->pendingUploadDir, 0775, true);
        }
    }

    /**
     * Opens (rotating to a new file first if the current one is already over
     * the limit) the log file this request should write to, and returns a
     * handle holding a shared lock on it. The caller MUST call release()
     * exactly once when done — ideally in a finally block — so the file can
     * eventually be closed for upload.
     *
     * @return resource
     */
    public function pin()
    {
        $pointerHandle = fopen($this->pointerFile, 'c+');
        flock($pointerHandle, LOCK_EX);

        rewind($pointerHandle);
        $currentFilename = trim((string) stream_get_contents($pointerHandle));

        $needsRotation = $currentFilename === ''
            || !is_file($this->activeDir . '/' . $currentFilename)
            || filesize($this->activeDir . '/' . $currentFilename) >= $this->maxBytes;

        if ($needsRotation) {
            $currentFilename = $this->newFilename();
            touch($this->activeDir . '/' . $currentFilename);

            ftruncate($pointerHandle, 0);
            rewind($pointerHandle);
            fwrite($pointerHandle, $currentFilename);
        }

        $handle = fopen($this->activeDir . '/' . $currentFilename, 'a');
        flock($handle, LOCK_SH);

        flock($pointerHandle, LOCK_UN);
        fclose($pointerHandle);

        $this->closeDrainedOrphans($currentFilename);

        return $handle;
    }

    /**
     * @param resource $handle a handle previously returned by pin()
     */
    /**
     * A blank line follows every record (request and response alike) so
     * consecutive records — and especially one call's pair vs. the next
     * call's — are visually distinct when scanning the file by eye. Written
     * as a single fwrite() call so O_APPEND keeps it atomic against other
     * concurrent writers sharing this file.
     */
    public function append($handle, array $entry): void
    {
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        fwrite($handle, $line);
    }

    /**
     * @param resource $handle a handle previously returned by pin()
     */
    public function release($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Force-closes whatever file is currently active, regardless of size, and
     * clears the pointer so the next request starts a fresh file. Used by the
     * end-of-day sweep so a low-traffic file that never hit the size limit
     * still gets uploaded. If a request is still in-flight against it (holds
     * the shared lock), it's left alone for a later sweep/opportunistic scan
     * to pick up — never force-closed out from under a writer. Does not
     * dispatch an upload itself — the caller decides.
     */
    public function forceCloseActiveFile(): ?string
    {
        $pointerHandle = fopen($this->pointerFile, 'c+');
        flock($pointerHandle, LOCK_EX);

        rewind($pointerHandle);
        $currentFilename = trim((string) stream_get_contents($pointerHandle));
        ftruncate($pointerHandle, 0);

        flock($pointerHandle, LOCK_UN);
        fclose($pointerHandle);

        if ($currentFilename === '' || !is_file($this->activeDir . '/' . $currentFilename)) {
            return null;
        }

        $closed = $this->tryCloseFile($this->activeDir . '/' . $currentFilename);

        return $closed ? $this->pendingUploadDir . '/' . $currentFilename : null;
    }

    /** @return string[] absolute paths of unzipped logs still waiting in pending_upload */
    public function pendingLogFiles(): array
    {
        return glob($this->pendingUploadDir . '/*.log') ?: [];
    }

    /** @return string[] absolute paths of zips in pending_upload that failed to upload previously */
    public function pendingZipFiles(): array
    {
        return glob($this->pendingUploadDir . '/*.zip') ?: [];
    }

    /**
     * Scans active_dir for files other than the currently-pointed-to one
     * (i.e. superseded by an earlier rotation) and closes any that no
     * in-flight request still holds a shared lock on.
     */
    private function closeDrainedOrphans(string $currentFilename): void
    {
        foreach (glob($this->activeDir . '/*.log') ?: [] as $file) {
            if (basename($file) === $currentFilename) {
                continue;
            }
            if ($this->tryCloseFile($file)) {
                ($this->onFileClosed)($this->pendingUploadDir . '/' . basename($file));
            }
        }
    }

    /**
     * Attempts to claim exclusive ownership of $path (i.e. confirm no
     * in-flight request still holds it via pin()'s shared lock) and, if
     * successful, moves it into pending_upload. Never blocks.
     */
    private function tryCloseFile(string $path): bool
    {
        $handle = @fopen($path, 'r+');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        rename($path, $this->pendingUploadDir . '/' . basename($path));

        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }

    /**
     * Timestamp reflects when the file started being written (UTC). Colons
     * are avoided for cross-platform filename safety; the ULID suffix keeps
     * filenames unique even if two files open within the same second.
     */
    private function newFilename(): string
    {
        return sprintf('http_%s_%s.log', now('UTC')->format('Y-m-d_H-i-s'), (string) Str::ulid());
    }
}
