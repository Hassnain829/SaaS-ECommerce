<?php

namespace Tests\Feature;

use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\Catalog\ProductTaxableDefaultResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductTaxableFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_products_default_to_taxable(): void
    {
        $owner = $this->merchant('existing-tax@example.test');
        $store = $this->store($owner, 'Existing Tax Store');

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Legacy Tax Product',
            'slug' => 'legacy-tax-product-'.Str::random(6),
            'base_price' => 10,
            'sku' => 'LEGACY-TAX-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $this->assertTrue((bool) $product->fresh()->is_taxable);
    }

    public function test_product_create_uses_each_store_taxable_default(): void
    {
        $owner = $this->merchant('defaults@example.test');
        $storeA = $this->store($owner, 'Taxable Default Store');
        $storeB = $this->store($owner, 'Exempt Default Store');
        $storeA->taxSetting->update(['default_product_taxable' => true]);
        $storeB->taxSetting->update(['default_product_taxable' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('product.store'), $this->productCreatePayload('Taxable Default', 'TAX-DEFAULT-A'))
            ->assertRedirect(route('products'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('product.store'), $this->productCreatePayload('Exempt Default', 'TAX-DEFAULT-B'))
            ->assertRedirect(route('products'));

        $this->assertTrue((bool) Product::query()->where('store_id', $storeA->id)->where('sku', 'TAX-DEFAULT-A')->firstOrFail()->is_taxable);
        $this->assertFalse((bool) Product::query()->where('store_id', $storeB->id)->where('sku', 'TAX-DEFAULT-B')->firstOrFail()->is_taxable);
    }

    public function test_quick_create_and_onboarding_create_respect_false_default(): void
    {
        $owner = $this->merchant('quick-onboarding-tax@example.test');
        $store = $this->store($owner, 'Quick Exempt Store');
        $store->taxSetting->update(['default_product_taxable' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $this->productCreatePayload('Quick Exempt', 'QUICK-EXEMPT') + [
                '_open_add_product_modal' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingProductPayload('Onboarding Exempt', 'ONBOARD-EXEMPT'))
            ->assertRedirect();

        $this->assertFalse((bool) Product::query()->where('store_id', $store->id)->where('sku', 'QUICK-EXEMPT')->firstOrFail()->is_taxable);
        $this->assertFalse((bool) Product::query()->where('store_id', $store->id)->where('sku', 'ONBOARD-EXEMPT')->firstOrFail()->is_taxable);
    }

    public function test_simple_and_variant_import_product_creation_respects_false_default(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->merchant('import-tax@example.test');
        $store = $this->store($owner, 'Import Exempt Store');
        $store->taxSetting->update(['default_product_taxable' => false]);

        $simple = UploadedFile::fake()->createWithContent('simple-tax.csv', "Title,SKU,Price,Stock\nImported Simple,IMP-TAX-1,12,5\n");
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $simple])
            ->assertRedirect();
        $simpleImport = ProductImport::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $simpleImport->id]))
            ->assertRedirect();

        $variantCsv = <<<'CSV'
parent_sku,product_name,opt1n,opt1v,vsku,vprice,vstock
IMP-TAX-PARENT,Imported Variant,Size,S,IMP-TAX-V1,15,4
CSV;
        $variant = UploadedFile::fake()->createWithContent('variant-tax.csv', $variantCsv);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $variant])
            ->assertRedirect();
        $variantImport = ProductImport::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $variantImport->id]), [
                'column_mapping' => [
                    'parent_sku' => 'parent_sku',
                    'product_name' => 'product_name',
                    'option_1_name' => 'opt1n',
                    'option_1_value' => 'opt1v',
                    'variant_sku' => 'vsku',
                    'variant_price' => 'vprice',
                    'variant_stock' => 'vstock',
                ],
            ])
            ->assertRedirect(route('products.import.preview', ['productImportId' => $variantImport->id]));
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $variantImport->id]))
            ->assertRedirect();

        $this->assertFalse((bool) Product::query()->where('store_id', $store->id)->where('sku', 'IMP-TAX-1')->firstOrFail()->is_taxable);
        $this->assertFalse((bool) Product::query()->where('store_id', $store->id)->where('sku', 'IMP-TAX-PARENT')->firstOrFail()->is_taxable);
    }

    public function test_product_create_respects_explicit_taxable_override_against_store_default(): void
    {
        $owner = $this->merchant('override-tax@example.test');
        $store = $this->store($owner, 'Override Tax Store');
        $store->taxSetting->update(['default_product_taxable' => true]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $this->productCreatePayload('Explicit Exempt', 'EXPLICIT-OFF') + [
                'is_taxable' => '0',
            ])
            ->assertRedirect(route('products'));

        $store->taxSetting->update(['default_product_taxable' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $this->productCreatePayload('Explicit Taxable', 'EXPLICIT-ON') + [
                'is_taxable' => '1',
            ])
            ->assertRedirect(route('products'));

        $this->assertFalse((bool) Product::query()->where('store_id', $store->id)->where('sku', 'EXPLICIT-OFF')->firstOrFail()->is_taxable);
        $this->assertTrue((bool) Product::query()->where('store_id', $store->id)->where('sku', 'EXPLICIT-ON')->firstOrFail()->is_taxable);
    }

    public function test_product_taxable_flag_can_be_edited_and_unrelated_updates_preserve_it(): void
    {
        $owner = $this->merchant('edit-tax@example.test');
        $store = $this->store($owner, 'Edit Tax Store');
        $product = $this->product($store, ['is_taxable' => true]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product, [
                'is_taxable' => '0',
            ]))
            ->assertRedirect(route('products'));

        $this->assertFalse((bool) $product->fresh()->is_taxable);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product->fresh(), [
                'name' => 'Name Changed Only',
            ], includeTaxable: false))
            ->assertRedirect(route('products'));

        $this->assertFalse((bool) $product->fresh()->is_taxable);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product->fresh(), [
                'is_taxable' => '1',
            ]))
            ->assertRedirect(route('products'));

        $this->assertTrue((bool) $product->fresh()->is_taxable);
    }

    public function test_staff_and_cross_store_requests_cannot_mutate_product_taxability(): void
    {
        $owner = $this->merchant('tax-owner@example.test');
        $staff = $this->merchant('tax-staff@example.test');
        $store = $this->store($owner, 'Protected Tax Store');
        $otherStore = $this->store($owner, 'Other Protected Tax Store');
        $this->attach($store, $staff, Store::ROLE_STAFF);
        $product = $this->product($store, ['is_taxable' => true]);
        $otherProduct = $this->product($otherStore, ['is_taxable' => true]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSeeText('Platform tax')
            ->assertSeeText('Tax applies');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product, [
                'is_taxable' => '0',
            ]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $otherProduct->id]), $this->productUpdatePayload($otherProduct, [
                'is_taxable' => '0',
            ]))
            ->assertNotFound();

        $this->assertTrue((bool) $product->fresh()->is_taxable);
        $this->assertTrue((bool) $otherProduct->fresh()->is_taxable);
    }

    public function test_resolver_missing_setting_falls_back_true_without_creating_setting(): void
    {
        $owner = $this->merchant('missing-tax-setting@example.test');
        $store = $this->store($owner, 'Missing Tax Setting Resolver Store');
        TaxSetting::query()->where('store_id', $store->id)->delete();

        $this->assertTrue(app(ProductTaxableDefaultResolver::class)->forStore($store));
        $this->assertSame(0, TaxSetting::query()->where('store_id', $store->id)->count());
    }

    public function test_external_checkout_preserves_supplied_tax_even_when_product_is_not_taxable(): void
    {
        [$store, $token] = $this->tokenedStore('External Product Tax Preserve Store');
        $store->taxSetting->update([
            'enabled' => true,
            'default_product_taxable' => false,
            'prices_include_tax' => true,
            'shipping_taxable' => true,
        ]);
        TaxRate::query()->create([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Ignored Platform Rate',
            'rate_percent' => '25.0000',
            'priority' => 100,
            'is_active' => true,
        ]);
        $product = $this->product($store, ['is_taxable' => false, 'price' => 12, 'stock' => 5]);
        $variant = $product->variants()->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->externalPayload($variant, [
                'totals' => [
                    'subtotal' => 24.00,
                    'shipping' => 4.44,
                    'tax' => 7.77,
                    'discount' => 1.11,
                    'grand_total' => 35.10,
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('order.total', '35.10');

        $this->assertDatabaseHas('orders', [
            'store_id' => $store->id,
            'tax' => 7.77,
            'shipping' => 4.44,
            'discount' => 1.11,
            'grand_total' => 35.10,
        ]);
        $this->assertSame(0, OrderTaxLine::query()->where('store_id', $store->id)->count());
    }

    private function productCreatePayload(string $name, string $sku): array
    {
        return [
            'name' => $name,
            'description' => $name.' description.',
            'bulk_price' => '12.00',
            'sku' => $sku,
            'product_type' => 'physical',
            'bulk_stock' => 5,
            'stock_alert' => 1,
        ];
    }

    private function onboardingProductPayload(string $name, string $sku): array
    {
        return [
            'name' => $name,
            'description' => $name.' description.',
            'base_price' => '12.00',
            'sku' => $sku,
            'product_type' => 'physical',
            'default_stock' => 5,
            'stock_alert' => 1,
            'mode' => 'create',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function onboardingEditPayload(Product $product, array $overrides = [], bool $includeTaxable = true): array
    {
        $payload = [
            'mode' => 'edit',
            'name' => $product->name,
            'description' => $product->description,
            'base_price' => (string) $product->base_price,
            'sku' => $product->sku,
            'product_type' => $product->product_type,
            'default_stock' => 5,
            'stock_alert' => 1,
        ];

        if ($includeTaxable) {
            $payload['is_taxable'] = $product->is_taxable ? '1' : '0';
        }

        return array_merge($payload, $overrides);
    }

    private function assertProductTaxableCheckboxChecked(string $html, string $inputId, bool $checked): void
    {
        $pattern = '/id="'.preg_quote($inputId, '/').'"[^>]*\bchecked\b/s';

        if ($checked) {
            $this->assertMatchesRegularExpression($pattern, $html);
        } else {
            $this->assertDoesNotMatchRegularExpression($pattern, $html);
        }
    }

    private function productUpdatePayload(Product $product, array $overrides = [], bool $includeTaxable = true): array
    {
        $payload = [
            'name' => $overrides['name'] ?? $product->name,
            'description' => $overrides['description'] ?? $product->description,
            'base_price' => $overrides['base_price'] ?? (string) $product->base_price,
            'sku' => $overrides['sku'] ?? $product->sku,
            'product_type' => $overrides['product_type'] ?? $product->product_type,
            'stock_alert' => $overrides['stock_alert'] ?? data_get($product->meta, 'stock_alert', 1),
        ];

        if ($includeTaxable) {
            $payload['is_taxable'] = $overrides['is_taxable'] ?? ($product->is_taxable ? '1' : '0');
        }

        return $payload;
    }

    private function tokenedStore(string $name): array
    {
        $owner = $this->merchant(Str::slug($name).'@example.test');
        $store = $this->store($owner, $name);
        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $token, $owner];
    }

    private function externalPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'WEB-'.Str::upper(Str::random(8)),
            'external_checkout_reference' => 'checkout-'.Str::random(8),
            'payment_status' => 'paid',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-'.Str::random(8),
            'currency_code' => 'USD',
            'shipping_total' => 4.50,
            'tax_total' => 1.50,
            'discount_total' => 2.00,
            'customer' => [
                'full_name' => 'External Buyer',
                'email' => 'external.taxable.flag@example.test',
                'phone' => '+15550199',
            ],
            'shipping_address' => [
                'name' => 'External Buyer',
                'address_line1' => '45 External Road',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'phone' => '+15550199',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => '12.00',
                    'external_line_id' => 'line-1',
                ],
            ],
        ], $overrides);
    }

    private function product(Store $store, array $overrides = []): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Tax Product',
            'slug' => 'tax-product-'.Str::random(6),
            'description' => 'Tax product description.',
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'TAX-PROD-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => $overrides['is_taxable'] ?? true,
            'meta' => ['stock_alert' => 1],
        ]);

        ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return $product;
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function store(User $owner, string $name): Store
    {
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return $store;
    }

    public function test_onboarding_explicit_true_and_false_overrides_respect_store_default(): void
    {
        $owner = $this->merchant('onboarding-override-tax@example.test');
        $store = $this->store($owner, 'Onboarding Override Tax Store');
        $store->taxSetting->update(['default_product_taxable' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingProductPayload('Onboarding Explicit On', 'ONBOARD-ON') + [
                'is_taxable' => '1',
            ])
            ->assertRedirect();

        $store->taxSetting->update(['default_product_taxable' => true]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingProductPayload('Onboarding Explicit Off', 'ONBOARD-OFF') + [
                'is_taxable' => '0',
            ])
            ->assertRedirect();

        $this->assertTrue((bool) Product::query()->where('store_id', $store->id)->where('sku', 'ONBOARD-ON')->firstOrFail()->is_taxable);
        $this->assertFalse((bool) Product::query()->where('store_id', $store->id)->where('sku', 'ONBOARD-OFF')->firstOrFail()->is_taxable);
    }

    public function test_onboarding_product_form_renders_taxable_control(): void
    {
        $owner = $this->merchant('onboarding-ui-tax@example.test');
        $store = $this->store($owner, 'Onboarding UI Tax Store');
        $store->taxSetting->update(['default_product_taxable' => true, 'enabled' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->get(route('onboarding-Step2-AddProductVariations'))
            ->assertOk()
            ->assertSee('Charge tax on this product', false)
            ->assertSee('Store default:', false)
            ->assertSee('Taxable', false)
            ->assertSee('platform checkout tax is currently disabled', false);
    }

    public function test_existing_product_unchanged_when_store_default_changes(): void
    {
        $owner = $this->merchant('default-shift-tax@example.test');
        $store = $this->store($owner, 'Default Shift Tax Store');

        $store->taxSetting->update(['default_product_taxable' => true]);
        $taxableProduct = $this->product($store, ['is_taxable' => true, 'sku' => 'EXPLICIT-TAXABLE']);

        $store->taxSetting->update(['default_product_taxable' => false]);
        $this->assertTrue((bool) $taxableProduct->fresh()->is_taxable);

        $exemptProduct = $this->product($store, ['is_taxable' => false, 'sku' => 'EXPLICIT-EXEMPT']);
        $store->taxSetting->update(['default_product_taxable' => true]);
        $this->assertFalse((bool) $exemptProduct->fresh()->is_taxable);
    }

    public function test_onboarding_edit_renders_actual_taxable_true(): void
    {
        $owner = $this->merchant('onboarding-edit-tax-true@example.test');
        $store = $this->store($owner, 'Onboarding Edit Tax True Store');
        $store->taxSetting->update(['default_product_taxable' => false]);
        $product = $this->product($store, ['is_taxable' => true, 'sku' => 'ONBOARD-EDIT-TRUE']);

        $html = $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->get(route('onboarding-Step2-AddProductVariations'))
            ->assertOk()
            ->getContent();

        $this->assertProductTaxableCheckboxChecked($html, 'onboarding-product-is-taxable', true);
    }

    public function test_onboarding_edit_renders_actual_taxable_false(): void
    {
        $owner = $this->merchant('onboarding-edit-tax-false@example.test');
        $store = $this->store($owner, 'Onboarding Edit Tax False Store');
        $store->taxSetting->update(['default_product_taxable' => true]);
        $product = $this->product($store, ['is_taxable' => false, 'sku' => 'ONBOARD-EDIT-FALSE']);

        $html = $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->get(route('onboarding-Step2-AddProductVariations'))
            ->assertOk()
            ->getContent();

        $this->assertProductTaxableCheckboxChecked($html, 'onboarding-product-is-taxable', false);
    }

    public function test_onboarding_edit_explicit_true_persists(): void
    {
        $owner = $this->merchant('onboarding-persist-true@example.test');
        $store = $this->store($owner, 'Onboarding Persist True Store');
        $product = $this->product($store, ['is_taxable' => false, 'sku' => 'ONBOARD-PERSIST-TRUE']);

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingEditPayload($product, [
                'is_taxable' => '1',
            ]))
            ->assertRedirect();

        $this->assertTrue((bool) $product->fresh()->is_taxable);
    }

    public function test_onboarding_edit_explicit_false_persists(): void
    {
        $owner = $this->merchant('onboarding-persist-false@example.test');
        $store = $this->store($owner, 'Onboarding Persist False Store');
        $product = $this->product($store, ['is_taxable' => true, 'sku' => 'ONBOARD-PERSIST-FALSE']);

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingEditPayload($product, [
                'is_taxable' => '0',
            ]))
            ->assertRedirect();

        $this->assertFalse((bool) $product->fresh()->is_taxable);
    }

    public function test_onboarding_edit_omitted_taxable_preserves_existing_value(): void
    {
        $owner = $this->merchant('onboarding-omit-tax@example.test');
        $store = $this->store($owner, 'Onboarding Omit Tax Store');
        $product = $this->product($store, ['is_taxable' => false, 'sku' => 'ONBOARD-OMIT-TAX']);

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingEditPayload($product, [
                'name' => 'Renamed Without Tax Field',
            ], includeTaxable: false))
            ->assertRedirect();

        $this->assertFalse((bool) $product->fresh()->is_taxable);
    }

    public function test_onboarding_edit_store_default_does_not_overwrite_existing_product_taxability(): void
    {
        $owner = $this->merchant('onboarding-default-no-overwrite@example.test');
        $store = $this->store($owner, 'Onboarding Default No Overwrite Store');
        $store->taxSetting->update(['default_product_taxable' => true]);
        $product = $this->product($store, ['is_taxable' => false, 'sku' => 'ONBOARD-NO-OVERWRITE']);

        $store->taxSetting->update(['default_product_taxable' => true]);

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingEditPayload($product, [
                'name' => 'Updated Name Only',
            ], includeTaxable: false))
            ->assertRedirect();

        $this->assertFalse((bool) $product->fresh()->is_taxable);
    }

    public function test_onboarding_create_validation_failure_preserves_unchecked_taxable(): void
    {
        $owner = $this->merchant('onboarding-old-false@example.test');
        $store = $this->store($owner, 'Onboarding Old False Store');
        $store->taxSetting->update(['default_product_taxable' => true]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->from(route('onboarding-Step2-AddProductVariations'))
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingProductPayload('', 'ONBOARD-OLD-FALSE') + [
                'is_taxable' => '0',
            ]);

        $response->assertSessionHasErrors('name');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->getContent();

        $this->assertProductTaxableCheckboxChecked($html, 'onboarding-product-is-taxable', false);
    }

    public function test_onboarding_create_validation_failure_preserves_checked_taxable(): void
    {
        $owner = $this->merchant('onboarding-old-true@example.test');
        $store = $this->store($owner, 'Onboarding Old True Store');
        $store->taxSetting->update(['default_product_taxable' => false]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->from(route('onboarding-Step2-AddProductVariations'))
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingProductPayload('', 'ONBOARD-OLD-TRUE') + [
                'is_taxable' => '1',
            ]);

        $response->assertSessionHasErrors('name');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->getContent();

        $this->assertProductTaxableCheckboxChecked($html, 'onboarding-product-is-taxable', true);
    }

    public function test_onboarding_edit_validation_failure_preserves_unchecked_taxable_change(): void
    {
        $owner = $this->merchant('onboarding-edit-old-false@example.test');
        $store = $this->store($owner, 'Onboarding Edit Old False Store');
        $product = $this->product($store, ['is_taxable' => true, 'sku' => 'ONBOARD-EDIT-OLD-FALSE']);

        $response = $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->from(route('onboarding-Step2-AddProductVariations'))
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingEditPayload($product, [
                'name' => '',
                'is_taxable' => '0',
            ]));

        $response->assertSessionHasErrors('name');

        $html = $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->getContent();

        $this->assertProductTaxableCheckboxChecked($html, 'onboarding-product-is-taxable', false);
    }

    public function test_onboarding_edit_validation_failure_preserves_checked_taxable_change(): void
    {
        $owner = $this->merchant('onboarding-edit-old-true@example.test');
        $store = $this->store($owner, 'Onboarding Edit Old True Store');
        $product = $this->product($store, ['is_taxable' => false, 'sku' => 'ONBOARD-EDIT-OLD-TRUE']);

        $response = $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->from(route('onboarding-Step2-AddProductVariations'))
            ->post(route('onboarding-Step2-AddProductVariations.store'), $this->onboardingEditPayload($product, [
                'name' => '',
                'is_taxable' => '1',
            ]));

        $response->assertSessionHasErrors('name');

        $html = $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
                'onboarding_product_id' => $product->id,
            ])
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->getContent();

        $this->assertProductTaxableCheckboxChecked($html, 'onboarding-product-is-taxable', true);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }
}
