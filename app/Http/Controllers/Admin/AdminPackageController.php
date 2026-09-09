<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPackageController extends Controller
{
    public function index(): View
    {
        $packages = SaasPackage::query()
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin_view.billing.index', [
            'packages' => $packages,
        ]);
    }

    public function create(): View
    {
        return view('admin_view.billing.form', [
            'package' => new SaasPackage([
                'currency' => 'USD',
                'billing_interval' => SaasPackage::INTERVAL_MONTH,
                'is_active' => true,
                'sort_order' => 0,
                'price_cents' => 0,
            ]),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = SaasPackage::uniqueSlugFromName($data['name']);
        $data['features'] = $this->parseFeatures($data['features_text'] ?? null);
        unset($data['features_text'], $data['price']);

        SaasPackage::query()->create($data);

        return redirect()
            ->route('admin-billing')
            ->with('success', 'Package created.');
    }

    public function edit(SaasPackage $package): View
    {
        return view('admin_view.billing.form', [
            'package' => $package,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, SaasPackage $package): RedirectResponse
    {
        $data = $this->validated($request, $package);
        if (($data['slug'] ?? '') === '') {
            $data['slug'] = SaasPackage::uniqueSlugFromName($data['name'], $package->id);
        } else {
            $data['slug'] = Str::slug($data['slug']) ?: SaasPackage::uniqueSlugFromName($data['name'], $package->id);
        }
        $data['features'] = $this->parseFeatures($data['features_text'] ?? null);
        unset($data['features_text'], $data['price']);

        $package->fill($data);
        $package->save();

        return redirect()
            ->route('admin-billing')
            ->with('success', 'Package updated.');
    }

    public function toggleActive(SaasPackage $package): RedirectResponse
    {
        $package->is_active = ! $package->is_active;
        $package->save();

        return redirect()
            ->route('admin-billing')
            ->with('success', $package->is_active ? 'Package activated.' : 'Package deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?SaasPackage $package = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                Rule::unique('saas_packages', 'slug')->ignore($package?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'billing_interval' => ['required', Rule::in(SaasPackage::BILLING_INTERVALS)],
            'currency' => ['required', 'string', 'size:3'],
            'features_text' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $validated['price_cents'] = (int) round(((float) $validated['price']) * 100);
        $validated['currency'] = strtoupper($validated['currency']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    /**
     * @return list<string>|null
     */
    protected function parseFeatures(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $features = array_values(array_filter(array_map(
            static fn ($line) => trim((string) $line),
            $lines
        ), static fn ($line) => $line !== ''));

        return $features === [] ? null : $features;
    }
}
