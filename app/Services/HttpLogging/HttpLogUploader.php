<?php

namespace App\Services\HttpLogging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Zips a closed local http-log file and uploads it to
 * SkillMatrixLogs/{UTC date}/{file}.zip on Google Drive, deleting the local
 * copy only once the upload has actually succeeded. Never throws — a logging
 * pipeline failure must never affect application behavior; failures are
 * reported via Log::error and the local file is left for the next retry
 * (the daily sweep command re-scans pending_upload for leftovers).
 */
class HttpLogUploader
{
    public function zipAndUpload(string $localLogPath): void
    {
        $zipPath = preg_replace('/\.log$/', '.zip', $localLogPath);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Could not create zip archive at {$zipPath}");
            }
            $zip->addFile($localLogPath, basename($localLogPath));
            $zip->close();

            unlink($localLogPath);
        } catch (\Throwable $e) {
            Log::error('HTTP log zip failed, will retry in the daily sweep', [
                'file' => $localLogPath,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $this->uploadZip($zipPath);
    }

    public function uploadZip(string $localZipPath): void
    {
        $remotePath = $this->remotePathFor($localZipPath);

        try {
            $stream = fopen($localZipPath, 'r');
            Storage::disk(config('http_logging.drive_disk'))->put($remotePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            unlink($localZipPath);
        } catch (\Throwable $e) {
            Log::error('HTTP log upload to Google Drive failed, will retry in the daily sweep', [
                'file' => $localZipPath,
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function remotePathFor(string $localZipPath): string
    {
        $basename = basename($localZipPath);

        $date = preg_match('/^http_(\d{4}-\d{2}-\d{2})_/', $basename, $matches)
            ? $matches[1]
            : now('UTC')->format('Y-m-d');

        return config('http_logging.drive_root_folder') . "/{$date}/{$basename}";
    }
}
