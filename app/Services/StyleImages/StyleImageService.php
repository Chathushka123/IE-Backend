<?php

namespace App\Services\StyleImages;

use App\Style;
use App\StyleImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores style images on the configured Flysystem disk (Google Drive by default) and
 * tracks them in `style_images`. Layout on the disk:
 *   {root_folder}/{style_id}/{uuid}.{ext}        original
 *   {root_folder}/{style_id}/thumbs/{uuid}.jpg   gallery thumbnail
 * Random UUID names mean a path's bytes never change, so responses can be cached hard,
 * and two uploads of "IMG_0001.jpg" can never collide.
 */
class StyleImageService
{
    public function __construct(private ImageThumbnailer $thumbnailer) {}

    /**
     * All-or-nothing: if any file fails to upload, every file already written by this
     * call is removed from the disk and no rows are kept.
     *
     * @param  UploadedFile[]  $files
     * @return Collection<int, StyleImage>
     */
    public function upload(Style $style, array $files, ?int $userId): Collection
    {
        $disk = config('style_images.disk');
        $writtenPaths = [];

        try {
            return DB::transaction(function () use ($style, $files, $userId, $disk, &$writtenPaths) {
                $created = collect();

                foreach ($files as $file) {
                    $created->push($this->storeOne($style, $file, $userId, $disk, $writtenPaths));
                }

                return $created;
            });
        } catch (Throwable $e) {
            $this->deleteFiles($disk, $writtenPaths);

            throw $e;
        }
    }

    /** Removes the row first so a failed disk delete can only leave an orphan file, never a broken row. */
    public function delete(StyleImage $image): void
    {
        $disk = $image->disk;
        $paths = array_filter([$image->path, $image->thumbnail_path]);

        $image->delete();

        $this->deleteFiles($disk, $paths);
    }

    private function storeOne(Style $style, UploadedFile $file, ?int $userId, string $disk, array &$writtenPaths): StyleImage
    {
        $mime = $file->getMimeType();
        $extension = $this->extensionFor($mime);
        $name = (string) Str::uuid();
        $folder = config('style_images.root_folder').'/'.$style->id;

        $path = "{$folder}/{$name}.{$extension}";
        $this->putFile($disk, $path, $file->getRealPath());
        $writtenPaths[] = $path;

        $thumbnailPath = null;
        $thumbnail = $this->thumbnailer->make(
            $file->getRealPath(),
            $mime,
            config('style_images.thumbnail_max_side'),
            config('style_images.thumbnail_quality')
        );
        if ($thumbnail !== null) {
            $thumbnailPath = "{$folder}/thumbs/{$name}.jpg";
            Storage::disk($disk)->put($thumbnailPath, $thumbnail);
            $writtenPaths[] = $thumbnailPath;
        }

        return StyleImage::create([
            'style_id' => $style->id,
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'created_by_id' => $userId,
        ]);
    }

    /** Streams from the temp file so a 5 MB photo is never held in memory twice. */
    private function putFile(string $disk, string $path, string $localPath): void
    {
        $stream = fopen($localPath, 'r');

        try {
            Storage::disk($disk)->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function deleteFiles(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $e) {
                Log::warning('Could not delete style image file from storage', [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
