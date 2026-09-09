@extends('layouts.admin.admin-Sidebar')

@section('title', ($isEdit ? 'Edit package' : 'New package').' — '.config('app.name'))

@section('content')
    @php
        $featuresText = old('features_text');
        if ($featuresText === null) {
            $featuresText = is_array($package->features) ? implode("\n", $package->features) : '';
        }
        $priceValue = old('price', number_format(($package->price_cents ?? 0) / 100, 2, '.', ''));
    @endphp

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('admin-billing') }}" class="text-sm font-medium text-brand hover:underline">← Packages</a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-900">{{ $isEdit ? 'Edit package' : 'New package' }}</h1>
            <p class="mt-1 text-sm text-stone-600">Pricing is stored for admin display and future billing — merchants are not charged from this screen.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ $isEdit ? route('admin-billing.packages.update', $package) : route('admin-billing.packages.store') }}"
              class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $package->name) }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="slug" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Slug (optional)</label>
                <input type="text" name="slug" id="slug" value="{{ old('slug', $package->slug) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Description</label>
                <textarea name="description" id="description" rows="3" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $package->description) }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="price" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Price</label>
                    <input type="number" step="0.01" min="0" name="price" id="price" value="{{ $priceValue }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="currency" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Currency</label>
                    <input type="text" name="currency" id="currency" maxlength="3" value="{{ old('currency', $package->currency ?: 'USD') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm uppercase">
                </div>
                <div>
                    <label for="billing_interval" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Interval</label>
                    <select name="billing_interval" id="billing_interval" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm" required>
                        @foreach (\App\Models\SaasPackage::BILLING_INTERVALS as $interval)
                            <option value="{{ $interval }}" @selected(old('billing_interval', $package->billing_interval) === $interval)>{{ $interval }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="sort_order" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Sort order</label>
                    <input type="number" min="0" name="sort_order" id="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-stone-800">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-stone-300" @checked(old('is_active', $package->is_active))>
                        Active (available to assign)
                    </label>
                </div>
            </div>

            <div>
                <label for="features_text" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Feature bullets (one per line)</label>
                <textarea name="features_text" id="features_text" rows="5" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ $featuresText }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">{{ $isEdit ? 'Save package' : 'Create package' }}</button>
                <a href="{{ route('admin-billing') }}" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-50">Cancel</a>
            </div>
        </form>
    </div>
@endsection
