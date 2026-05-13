<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Catalog\ProductCatalogImageDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCatalogImageDownloaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_public_ip_image_url(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://1.1.1.1/ok.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('x', 40), 200, ['Content-Type' => 'image/png']),
        ]);

        $store = $this->createStoreForDownloader();

        $path = app(ProductCatalogImageDownloader::class)->downloadRemoteImageToDisk($store, 'https://1.1.1.1/ok.png');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_rejects_non_image_content_type(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://1.1.1.1/data' => Http::response('{}', 200, ['Content-Type' => 'application/json']),
        ]);

        $store = $this->createStoreForDownloader();

        $path = app(ProductCatalogImageDownloader::class)->downloadRemoteImageToDisk($store, 'https://1.1.1.1/data');

        $this->assertNull($path);
    }

    public function test_rejects_localhost_without_sending_request(): void
    {
        Storage::fake('public');
        Http::fake();

        $store = $this->createStoreForDownloader();

        $path = app(ProductCatalogImageDownloader::class)->downloadRemoteImageToDisk($store, 'http://127.0.0.1/x.png');

        $this->assertNull($path);
        Http::assertNothingSent();
    }

    public function test_rejects_private_ip_without_sending_request(): void
    {
        Storage::fake('public');
        Http::fake();

        $store = $this->createStoreForDownloader();

        $path = app(ProductCatalogImageDownloader::class)->downloadRemoteImageToDisk($store, 'http://10.0.0.5/x.png');

        $this->assertNull($path);
        Http::assertNothingSent();
    }

    private function createStoreForDownloader(): Store
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $store = Store::create([
            'user_id' => $user->id,
            'name' => 'DL Store',
            'slug' => 'dl-store-'.uniqid(),
            'logo' => null,
            'address' => 'Test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($user->id, ['role' => 'owner']);

        return $store;
    }
}
