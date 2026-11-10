<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StyleImages\StyleImageService;
use App\Style;
use App\StyleImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Image attachments for a style. Style lookups go through the Style model's factory
 * scope, so a user can only see/modify images of styles in their own factories (an
 * out-of-scope style behaves exactly like a missing one: 404).
 */
class StyleImageController extends Controller
{
    public function __construct(private StyleImageService $images) {}

    public function index($styleId)
    {
        $style = Style::findOrFail($styleId);

        return response()->json(['status' => 'success', 'data' => $style->images]);
    }

    public function store(Request $request, $styleId)
    {
        $style = Style::findOrFail($styleId);

        $request->validate([
            'images' => 'required|array|min:1|max:'.config('style_images.max_files_per_request'),
            'images.*' => 'file|mimes:'.implode(',', config('style_images.allowed_mimes')).'|max:'.config('style_images.max_file_kb'),
        ], [
            'images.*.mimes' => 'Only JPG, PNG or WebP images are allowed.',
            'images.*.max' => 'Each image must be '.round(config('style_images.max_file_kb') / 1024, 1).' MB or smaller.',
            'images.*.uploaded' => 'An image failed to upload. It may be larger than the server allows.',
        ]);

        $files = $request->file('images');
        $limit = config('style_images.max_files_per_style');
        if ($style->images()->count() + count($files) > $limit) {
            throw ValidationException::withMessages([
                'images' => "A style can have at most {$limit} images.",
            ]);
        }

        try {
            $created = $this->images->upload($style, $files, Auth::id());
        } catch (Throwable $e) {
            Log::error('Style image upload failed', ['style_id' => $style->id, 'error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Could not save the images to storage. Nothing was uploaded — please try again.',
            ], 500);
        }

        return response()->json(['status' => 'success', 'data' => $created->values()], 201);
    }

    /**
     * Streams the image bytes. Files are not publicly addressable (Drive stays private),
     * so the browser fetches this with the bearer token and shows it from a blob URL.
     * `?variant=thumb` serves the small gallery thumbnail, falling back to the original
     * for images that have none.
     */
    public function file(Request $request, $styleId, $imageId)
    {
        $image = $this->findImage($styleId, $imageId);

        $wantsThumb = $request->query('variant') === 'thumb' && $image->thumbnail_path;
        $path = $wantsThumb ? $image->thumbnail_path : $image->path;
        $mime = $wantsThumb ? 'image/jpeg' : $image->mime_type;

        try {
            $stream = Storage::disk($image->disk)->readStream($path);
        } catch (Throwable $e) {
            $stream = null;
            Log::error('Style image file could not be read from storage', [
                'image_id' => $image->id, 'disk' => $image->disk, 'path' => $path, 'error' => $e->getMessage(),
            ]);
        }

        if (! is_resource($stream)) {
            return response()->json(['status' => 'error', 'message' => 'The image file could not be loaded.'], 404);
        }

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($image->file_name)).'"',
            // private: the response is per-user (auth required), so shared caches must not keep it.
            'Cache-Control' => 'private, max-age='.config('style_images.cache_seconds').', immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy($styleId, $imageId)
    {
        $image = $this->findImage($styleId, $imageId);

        $this->images->delete($image);

        return response()->json(['status' => 'success', 'message' => 'Image deleted']);
    }

    private function findImage($styleId, $imageId): StyleImage
    {
        $style = Style::findOrFail($styleId);

        return $style->images()->whereKey($imageId)->firstOrFail();
    }
}
