<?php

namespace Tests\Feature;

use App\StyleImage;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Support\ProductFamilies;

class StyleImageTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'style-images-test';

    protected function setUp(): void
    {
        parent::setUp();

        // A local fake stands in for Google Drive — same Flysystem API, no network.
        Storage::fake(self::DISK);
        config(['style_images.disk' => self::DISK]);
    }

    private function makeFactory(): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        return DB::table('factories')->insertGetId([
            'name' => 'Image Test Factory'.$unique,
            'code' => 'ITF'.$unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * User is factory-scoped too, and FactoryContext persists after the first request in a
     * test — so later fixtures must load their user without global scopes.
     * Style is factory-scoped, so the user must be linked to a factory the style belongs to. */
    private function headersForFactories(array $factoryIds): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Image Tester',
            'email' => 'image-tester-'.uniqid().'@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('factory_user')->insert(array_map(
            fn ($factoryId) => ['factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()],
            $factoryIds
        ));

        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser(User::withoutGlobalScopes()->find($userId)),
            'X-Factory-Ids' => implode(',', $factoryIds),
        ];
    }

    /** @return array{0:int,1:array} [styleId, headers of a user who can see it] */
    private function makeStyleWithAccess(): array
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        $groupId = DB::table('product_groups')->insertGetId(['name' => 'G'.$unique, 'code' => 'G'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'C'.$unique, 'code' => 'C'.$unique, 'product_family_id' => ProductFamilies::forGroup($groupId), 'created_at' => now(), 'updated_at' => now()]);
        $customerId = DB::table('customers')->insertGetId(['description' => 'Cu'.$unique, 'code' => 'CU'.$unique, 'created_at' => now(), 'updated_at' => now()]);

        $seasonId = DB::table('seasons')->insertGetId(['name' => 'Summer 27', 'code' => 'SU'.$unique, 'customer_id' => $customerId, 'created_at' => now(), 'updated_at' => now()]);

        $styleId = DB::table('styles')->insertGetId([
            'name' => 'Style '.$unique,
            'style_code' => 'ST'.$unique,
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $factoryId = $this->makeFactory();
        DB::table('factory_style')->insert(['factory_id' => $factoryId, 'style_id' => $styleId, 'created_at' => now(), 'updated_at' => now()]);

        return [$styleId, $this->headersForFactories([$factoryId])];
    }

    private function jpeg(string $name = 'front.jpg', int $kb = 10): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 600)->size($kb);
    }

    // ── upload ────────────────────────────────────────────────────────────────

    public function testUploadsMultipleImagesAndStoresOriginalAndThumbnail()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();

        $response = $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", [
            'images' => [$this->jpeg('front.jpg'), $this->jpeg('back.jpg')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('status', 'success')->assertJsonCount(2, 'data');
        $this->assertSame(2, StyleImage::where('style_id', $styleId)->count());

        $image = StyleImage::where('style_id', $styleId)->where('file_name', 'front.jpg')->first();
        Storage::disk(self::DISK)->assertExists($image->path);
        Storage::disk(self::DISK)->assertExists($image->thumbnail_path);
        $this->assertSame("StyleImages/{$styleId}/", substr($image->path, 0, strlen("StyleImages/{$styleId}/")));
    }

    public function testResponseNeverExposesStoragePaths()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();

        $response = $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", [
            'images' => [$this->jpeg()],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonMissingPath('data.0.disk')
            ->assertJsonMissingPath('data.0.thumbnail_path')
            ->assertJsonPath('data.0.has_thumbnail', true);
    }

    public function testRejectsRequestWithNoImages()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();

        $this->withHeaders($headers)->postJson("/api/v1/styles/{$styleId}/images", [])
            ->assertStatus(406)->assertJsonPath('message', 'The images field is required.');
    }

    public function testRejectsNonImageFile()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", [
            'images' => [UploadedFile::fake()->create('evil.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(406)->assertJsonPath('message', 'Only JPG, PNG or WebP images are allowed.');

        $this->assertSame(0, StyleImage::count());
    }

    public function testRejectsSvgBecauseItCanCarryScript()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$svg]], ['Accept' => 'application/json'])
            ->assertStatus(406);
    }

    public function testRejectsOversizedImage()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        $tooBig = config('style_images.max_file_kb') + 1;

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", [
            'images' => [$this->jpeg('huge.jpg', $tooBig)],
        ], ['Accept' => 'application/json'])->assertStatus(406)->assertJsonPath('status', 'error');
    }

    public function testRejectsMoreFilesThanAllowedPerRequest()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        $files = array_map(fn ($i) => $this->jpeg("p{$i}.jpg"), range(1, config('style_images.max_files_per_request') + 1));

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => $files], ['Accept' => 'application/json'])
            ->assertStatus(406)->assertJsonPath('status', 'error');
    }

    public function testRejectsUploadThatWouldExceedPerStyleLimit()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        config(['style_images.max_files_per_style' => 2]);

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg('a.jpg'), $this->jpeg('b.jpg')]], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg('c.jpg')]], ['Accept' => 'application/json'])
            ->assertStatus(406)->assertJsonPath('message', 'A style can have at most 2 images.');

        $this->assertSame(2, StyleImage::where('style_id', $styleId)->count());
    }

    public function testUploadIsAtomicWhenStorageFailsMidway()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();

        // Disk that succeeds for the first write then throws, simulating a Drive outage mid-request.
        $real = Storage::disk(self::DISK);
        $calls = 0;
        $flaky = \Mockery::mock($real)->makePartial();
        $flaky->shouldReceive('put')->andReturnUsing(function (...$args) use ($real, &$calls) {
            if (++$calls > 2) { // original + thumbnail of file 1 succeed, file 2 fails
                throw new \RuntimeException('Drive unavailable');
            }

            return $real->put(...$args);
        });
        Storage::shouldReceive('disk')->with(self::DISK)->andReturn($flaky);

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", [
            'images' => [$this->jpeg('a.jpg'), $this->jpeg('b.jpg')],
        ], ['Accept' => 'application/json'])->assertStatus(500)->assertJsonPath('status', 'error');

        $this->assertSame(0, StyleImage::count());
        $this->assertSame([], $real->allFiles());
    }

    // ── access ────────────────────────────────────────────────────────────────

    public function testRequiresAuthentication()
    {
        [$styleId] = $this->makeStyleWithAccess();

        $this->postJson("/api/v1/styles/{$styleId}/images", [])->assertStatus(401);
        $this->getJson("/api/v1/styles/{$styleId}/images")->assertStatus(401);
    }

    public function testStyleOutsideUsersFactoriesIsNotFound()
    {
        [$styleId] = $this->makeStyleWithAccess();
        $otherHeaders = $this->headersForFactories([$this->makeFactory()]);

        $this->withHeaders($otherHeaders)->getJson("/api/v1/styles/{$styleId}/images")->assertStatus(404);
        $this->withHeaders($otherHeaders)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg()]], ['Accept' => 'application/json'])
            ->assertStatus(404);
    }

    public function testUnknownStyleIsNotFound()
    {
        [, $headers] = $this->makeStyleWithAccess();

        $this->withHeaders($headers)->getJson('/api/v1/styles/999999/images')->assertStatus(404);
    }

    // ── list / file / delete ──────────────────────────────────────────────────

    public function testListsImagesOldestFirstAndEmptyStyleReturnsEmptyArray()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();

        $this->withHeaders($headers)->getJson("/api/v1/styles/{$styleId}/images")
            ->assertOk()->assertExactJson(['status' => 'success', 'data' => []]);

        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg('first.jpg'), $this->jpeg('second.jpg')]], ['Accept' => 'application/json']);

        $this->withHeaders($headers)->getJson("/api/v1/styles/{$styleId}/images")
            ->assertOk()->assertJsonPath('data.0.file_name', 'first.jpg')->assertJsonPath('data.1.file_name', 'second.jpg');
    }

    public function testStreamsOriginalAndThumbnailBytes()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg()]], ['Accept' => 'application/json']);
        $image = StyleImage::first();

        $original = $this->withHeaders($headers)->get("/api/v1/styles/{$styleId}/images/{$image->id}/file");
        $original->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('private', $original->headers->get('Cache-Control'));
        $this->assertSame(Storage::disk(self::DISK)->get($image->path), $original->streamedContent());

        $thumb = $this->withHeaders($headers)->get("/api/v1/styles/{$styleId}/images/{$image->id}/file?variant=thumb");
        $thumb->assertOk();
        $this->assertSame(Storage::disk(self::DISK)->get($image->thumbnail_path), $thumb->streamedContent());
    }

    public function testThumbnailVariantFallsBackToOriginalWhenNoThumbnailExists()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg()]], ['Accept' => 'application/json']);
        $image = StyleImage::first();
        Storage::disk(self::DISK)->delete($image->thumbnail_path);
        $image->update(['thumbnail_path' => null]);

        $response = $this->withHeaders($headers)->get("/api/v1/styles/{$styleId}/images/{$image->id}/file?variant=thumb");

        $this->assertSame(Storage::disk(self::DISK)->get($image->path), $response->streamedContent());
    }

    public function testFileMissingFromStorageReturns404()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg()]], ['Accept' => 'application/json']);
        $image = StyleImage::first();
        Storage::disk(self::DISK)->delete($image->path);

        $this->withHeaders($headers)->getJson("/api/v1/styles/{$styleId}/images/{$image->id}/file")->assertStatus(404);
    }

    public function testImageCannotBeReachedThroughAnotherStyle()
    {
        [$styleA, $headersA] = $this->makeStyleWithAccess();
        $this->withHeaders($headersA)->post("/api/v1/styles/{$styleA}/images", ['images' => [$this->jpeg()]], ['Accept' => 'application/json']);
        $image = StyleImage::first();
        [$styleB, $headersB] = $this->makeStyleWithAccess();

        $this->withHeaders($headersB)->get("/api/v1/styles/{$styleB}/images/{$image->id}/file")->assertStatus(404);
        $this->withHeaders($headersB)->deleteJson("/api/v1/styles/{$styleB}/images/{$image->id}")->assertStatus(404);
        $this->assertSame(1, StyleImage::count());
    }

    public function testDeleteRemovesRowAndBothFiles()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg()]], ['Accept' => 'application/json']);
        $image = StyleImage::first();

        $this->withHeaders($headers)->deleteJson("/api/v1/styles/{$styleId}/images/{$image->id}")
            ->assertOk()->assertJsonPath('status', 'success');

        $this->assertSame(0, StyleImage::count());
        Storage::disk(self::DISK)->assertMissing($image->path);
        Storage::disk(self::DISK)->assertMissing($image->thumbnail_path);
    }

    public function testDeletingStyleCascadesToItsImageRows()
    {
        [$styleId, $headers] = $this->makeStyleWithAccess();
        $this->withHeaders($headers)->post("/api/v1/styles/{$styleId}/images", ['images' => [$this->jpeg()]], ['Accept' => 'application/json']);

        DB::table('factory_style')->where('style_id', $styleId)->delete();
        DB::table('styles')->where('id', $styleId)->delete();

        $this->assertSame(0, StyleImage::count());
    }
}
