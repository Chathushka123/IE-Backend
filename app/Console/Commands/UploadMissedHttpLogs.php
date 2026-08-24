<?php

namespace App\Console\Commands;

use App\Services\HttpLogging\HttpLogFileManager;
use App\Services\HttpLogging\HttpLogUploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * End-of-day safety net (scheduled 23:45 UTC): force-closes whatever http-log
 * file is currently active — even if it never reached the size limit — and
 * retries any file left behind in pending_upload/ by a previous failed or
 * interrupted upload.
 */
class UploadMissedHttpLogs extends Command
{
    protected $signature = 'http-logs:upload-missed';

    protected $description = 'Close today\'s active HTTP log file and upload any http-log files missing from Google Drive';

    public function handle(HttpLogFileManager $fileManager, HttpLogUploader $uploader): int
    {
        $closed = $fileManager->forceCloseActiveFile();
        if ($closed !== null) {
            $this->info("Closed active log file: {$closed}");
        }

        $logFiles = $fileManager->pendingLogFiles();
        foreach ($logFiles as $logFile) {
            $this->info("Zipping and uploading: {$logFile}");
            $uploader->zipAndUpload($logFile);
        }

        $zipFiles = $fileManager->pendingZipFiles();
        foreach ($zipFiles as $zipFile) {
            $this->info("Retrying upload: {$zipFile}");
            $uploader->uploadZip($zipFile);
        }

        $remaining = count($fileManager->pendingLogFiles()) + count($fileManager->pendingZipFiles());
        if ($remaining > 0) {
            $this->warn("{$remaining} http-log file(s) still pending after this sweep.");
            Log::warning("HTTP log daily sweep finished with {$remaining} file(s) still unuploaded.");
        } else {
            $this->info('All http-log files uploaded.');
        }

        return self::SUCCESS;
    }
}
