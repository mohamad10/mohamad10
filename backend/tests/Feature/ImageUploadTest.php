<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
    }

    private function upload(int $w, int $h, string $name = 'photo.jpg'): string
    {
        return $this->postJson('/api/admin/uploads', ['file' => UploadedFile::fake()->image($name, $w, $h)])
            ->assertCreated()->json('path');
    }

    public function test_uploads_are_converted_to_webp(): void
    {
        $path = $this->upload(1600, 1000, 'photo.png');

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($path))['mime']);
    }

    public function test_huge_uploads_are_scaled_down(): void
    {
        $path = $this->upload(4000, 3000);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame([2560, 1920], [$w, $h]);
    }

    public function test_preset_variants_are_generated_cropped_and_cached(): void
    {
        $path = $this->upload(1600, 1200);

        $this->get("/storage/cache/avatar/256/{$path}")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $cached = "cache/avatar/256/{$path}";
        Storage::disk('public')->assertExists($cached);
        $this->assertSame([256, 256], array_slice(getimagesizefromstring(Storage::disk('public')->get($cached)), 0, 2));

        $this->get("/storage/cache/cover/800/{$path}")->assertOk();
        $this->assertSame([800, 500], array_slice(getimagesizefromstring(Storage::disk('public')->get("cache/cover/800/{$path}")), 0, 2));
    }

    public function test_small_images_are_never_upscaled(): void
    {
        $path = $this->upload(300, 300);

        $this->get("/storage/cache/cover/1600/{$path}")->assertOk();
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get("cache/cover/1600/{$path}"));
        $this->assertLessThanOrEqual(300, $w);
        $this->assertEqualsWithDelta(1.6, $w / $h, 0.02);
    }

    public function test_unknown_presets_and_sizes_are_rejected(): void
    {
        $path = $this->upload(800, 600);

        $this->get("/storage/cache/avatar/999/{$path}")->assertNotFound();
        $this->get("/storage/cache/huge/256/{$path}")->assertNotFound();
        $this->get('/storage/cache/avatar/256/uploads/missing.webp')->assertNotFound();
    }

    public function test_non_images_are_rejected(): void
    {
        $this->postJson('/api/admin/uploads', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertStatus(422);
    }
}
