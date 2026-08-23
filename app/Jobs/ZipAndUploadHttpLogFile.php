<?php

namespace App\Jobs;

use App\Services\HttpLogging\HttpLogUploader;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Deliberately NOT ShouldQueue: dispatched via ->afterResponse() so it always
 * runs in-process right after the triggering response is sent, regardless of
 * QUEUE_CONNECTION. Making it a real queued job would require a running
 * queue worker, which this app does not have.
 */
class ZipAndUploadHttpLogFile
{
    use Dispatchable;

    public function __construct(private readonly string $localLogPath)
    {
    }

    public function handle(HttpLogUploader $uploader): void
    {
        $uploader->zipAndUpload($this->localLogPath);
    }
}
