<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductRichTextWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_renders_html_description_and_short_description_safely(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'Rich Text Store');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Woo Import Product',
            'slug' => 'woo-import-product-'.fake()->unique()->numberBetween(1, 99999),
            'description' => '<p>Long copy</p><ul><li><b>Benefit</b></li></ul><script>alert(1)</script>',
            'base_price' => 19.99,
            'sku' => 'RICH-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'catalog' => [
                    'short_description' => '<span>Short intro</span>\n\n<ul><li>Point one</li></ul>',
                ],
            ],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product));

        $response->assertOk();
        $html = (string) $response->getContent();

        preg_match_all('/class="[^"]*product-rich-text[^"]*"[^>]*>(.*?)<\/div>/s', $html, $matches);
        $richBlocks = implode("\n", $matches[1] ?? []);

        $this->assertNotSame('', $richBlocks);
        $this->assertStringContainsString('Long copy', $richBlocks);
        $this->assertStringContainsString('<ul>', $richBlocks);
        $this->assertStringContainsString('<b>Benefit</b>', $richBlocks);
        $this->assertStringContainsString('Short intro', $richBlocks);
        $this->assertStringContainsString('Point one', $richBlocks);
        $this->assertStringNotContainsString('&lt;ul&gt;', $richBlocks);
        $this->assertStringNotContainsString('<script', strtolower($richBlocks));
        $this->assertStringNotContainsString('alert(1)', $richBlocks);
        $this->assertStringNotContainsString('\\n', $richBlocks);
    }

    public function test_description_preview_endpoint_returns_sanitized_html(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'Preview Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->postJson(route('products.description.preview'), [
                'content' => '<p>Hello</p><script>bad()</script>',
            ])
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => is_string($html)
                && str_contains($html, 'Hello')
                && ! str_contains(strtolower($html), '<script')
                && ! str_contains($html, 'bad()'));
    }

    public function test_product_update_persists_sanitized_html_description(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'Save Store');
        $product = $this->makeProduct($store, 'Editable Rich');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                '_workspace_return_product_id' => (string) $product->id,
                'name' => $product->name,
                'description' => '<p>Updated</p><script>x()</script>',
                'base_price' => $product->base_price,
                'sku' => $product->sku,
                'product_type' => 'physical',
                'stock_alert' => 1,
                'variation_types' => [],
            ])
            ->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertStringContainsString('<p>', (string) $product->description);
        $this->assertStringContainsString('Updated', (string) $product->description);
        $this->assertStringNotContainsString('<script', strtolower((string) $product->description));
    }

    private function merchantUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $owner, string $name): Store
    {
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    private function makeProduct(Store $store, string $name): Product
    {
        return Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => 'Plain text',
            'base_price' => 10,
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1, 99999),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
    }
}
