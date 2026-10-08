<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Modules\Menu\Support\MenuPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 4: menu photos are validated, re-encoded to WebP without metadata,
 * and stored on the public disk.
 */
class MenuPhotoUploadTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        Storage::fake('public');
    }

    /**
     * @return array<string, mixed>
     */
    private function form(?UploadedFile $photo): array
    {
        return array_filter([
            'slug' => 'foto-uji',
            'category_id' => $this->category()->id,
            'price' => 30000,
            'is_available' => '1',
            'sort_order' => 1,
            'translations' => [
                'id' => ['name' => 'Foto uji', 'description' => 'Deskripsi', 'aliases' => ''],
                'en' => ['name' => 'Photo test', 'description' => 'Description', 'aliases' => ''],
                'ja' => ['name' => '写真テスト', 'description' => '説明', 'aliases' => ''],
            ],
            'photo' => $photo,
        ], fn ($value) => $value !== null);
    }

    public function test_a_valid_photo_is_reencoded_as_webp_and_stored(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/menu', $this->form(UploadedFile::fake()->image('dish.jpg', 1000, 800)))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = MenuItem::query()->where('slug', 'foto-uji')->firstOrFail();
        $this->assertMatchesRegularExpression('#^menu/foto-uji-[0-9a-f]{12}\.webp$#', $item->photo_path);
        Storage::disk('public')->assertExists($item->photo_path);

        $bytes = Storage::disk('public')->get($item->photo_path);
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
    }

    public function test_metadata_is_dropped_by_the_reencode(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'photo');
        $image = imagecreatetruecolor(900, 700);
        imagejpeg($image, $path, 90);
        // Append a payload the way a malicious or private-data-carrying upload would.
        file_put_contents($path, "\nSECRET-GPS-DATA-12.34,56.78<?php echo 1; ?>", FILE_APPEND);

        $stored = app(MenuPhotos::class)->store(new UploadedFile($path, 'dish.jpg', 'image/jpeg', null, true), 'metadata-test');

        $bytes = Storage::disk('public')->get($stored);
        $this->assertStringNotContainsString('SECRET-GPS-DATA', $bytes);
        $this->assertStringNotContainsString('<?php', $bytes);
        @unlink($path);
    }

    public function test_large_photos_are_scaled_down(): void
    {
        $stored = app(MenuPhotos::class)->store(UploadedFile::fake()->image('big.png', 3200, 2400), 'big');

        $size = getimagesizefromstring(Storage::disk('public')->get($stored));
        $this->assertSame(MenuPhotos::MAX_SIDE, max($size[0], $size[1]));
        $this->assertEqualsWithDelta(4 / 3, $size[0] / $size[1], 0.01);
    }

    public function test_invalid_uploads_are_rejected_and_nothing_is_stored(): void
    {
        $admin = $this->admin();

        foreach ([
            'wrong type' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            'too large' => UploadedFile::fake()->image('huge.jpg', 1200, 900)->size(3000),
            'too small' => UploadedFile::fake()->image('tiny.jpg', 200, 150),
            'not an image' => UploadedFile::fake()->createWithContent('dish.jpg', 'definitely not an image'),
        ] as $case => $file) {
            $this->actingAs($admin)->post('/admin/menu', $this->form($file))->assertSessionHasErrors('photo');
            $this->assertDatabaseMissing('menu_items', ['slug' => 'foto-uji']);
            $this->assertSame([], Storage::disk('public')->allFiles(), $case);
        }
    }

    public function test_removing_or_replacing_a_photo_deletes_the_old_file(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/menu', $this->form(UploadedFile::fake()->image('a.jpg', 1000, 800)))->assertRedirect();
        $item = MenuItem::query()->where('slug', 'foto-uji')->firstOrFail();
        $first = $item->photo_path;

        $update = $this->form(UploadedFile::fake()->image('b.png', 1100, 900));
        unset($update['slug']);
        $this->actingAs($admin)->patch("/admin/menu/{$item->id}", $update)->assertSessionHasNoErrors();
        $second = $item->refresh()->photo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $remove = $this->form(null);
        unset($remove['slug']);
        $this->actingAs($admin)->patch("/admin/menu/{$item->id}", $remove + ['remove_photo' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($item->refresh()->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_stored_path_is_never_taken_from_the_request(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/menu', $this->form(UploadedFile::fake()->image('dish.jpg', 1000, 800)) + ['photo_path' => '../../.env'])
            ->assertRedirect();

        $this->assertStringStartsWith('menu/', MenuItem::query()->where('slug', 'foto-uji')->firstOrFail()->photo_path);
    }
}
