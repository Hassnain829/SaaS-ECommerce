@extends('layouts.user.user-sidebar')

@section('title', 'Product attributes')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto bg-[#F1F5F9]/50 p-4 lg:p-10">
        <div class="mx-auto max-w-6xl space-y-8">
            @include('user_view.partials.flash_success')

            <div class="flex flex-col gap-4 border-b border-[#E2E8F0] pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('products') }}" class="text-sm font-semibold text-[#0052CC] hover:underline">Back to products</a>
                    <h1 class="mt-3 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Product attributes</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-[#64748B]">
                        Attributes are structured product facts shoppers can filter or compare, such as material, capacity, color family, or ingredients.
                    </p>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="ml-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Add an attribute</h2>
                <form method="post" action="{{ route('catalog.attributes.store') }}" class="mt-5 grid gap-4 lg:grid-cols-12">
                    @csrf
                    <div class="lg:col-span-3">
                        <label for="attribute_name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Name</label>
                        <input id="attribute_name" name="name" value="{{ old('name') }}" placeholder="Material" class="w-full rounded-xl border border-[#CBD5E1] px-3 py-2.5 text-sm text-[#0F172A]">
                    </div>
                    <div class="lg:col-span-2">
                        <label for="display_type" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Display</label>
                        <select id="display_type" name="display_type" class="w-full rounded-xl border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm text-[#0F172A]">
                            <option value="text">Text</option>
                            <option value="select">Select</option>
                            <option value="color">Color</option>
                            <option value="number">Number</option>
                        </select>
                    </div>
                    <div class="lg:col-span-4">
                        <label for="attribute_terms" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Terms</label>
                        <input id="attribute_terms" name="terms" value="{{ old('terms') }}" placeholder="Cotton, Wool, Recycled" class="w-full rounded-xl border border-[#CBD5E1] px-3 py-2.5 text-sm text-[#0F172A]">
                    </div>
                    <div class="flex items-center gap-4 lg:col-span-2 lg:pt-6">
                        <label class="inline-flex items-center gap-2 text-sm text-[#334155]"><input type="checkbox" name="is_filterable" value="1" class="rounded border-[#CBD5E1] accent-[#0052CC]" checked> Filterable</label>
                        <label class="inline-flex items-center gap-2 text-sm text-[#334155]"><input type="checkbox" name="is_visible" value="1" class="rounded border-[#CBD5E1] accent-[#0052CC]" checked> Visible</label>
                    </div>
                    <div class="lg:col-span-1 lg:pt-6">
                        <button type="submit" class="w-full rounded-xl bg-[#0052CC] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#0047B3]">Save</button>
                    </div>
                </form>
            </section>

            <section class="space-y-4">
                @forelse ($attributes as $attribute)
                    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">{{ $attribute->name }}</h2>
                                    @if ($attribute->is_filterable)
                                        <span class="rounded-full bg-[#EEF4FF] px-2.5 py-1 text-xs font-semibold text-[#0052CC]">Filterable</span>
                                    @endif
                                    @if (! $attribute->is_visible)
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Hidden</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-[#64748B]">{{ ucfirst($attribute->display_type) }} · {{ $attribute->product_attributes_count }} product(s)</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse ($attribute->terms as $term)
                                        <span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-sm text-[#334155]">
                                            @if ($term->swatch_value)
                                                <span class="h-3 w-3 rounded-full border border-slate-200" style="background: {{ $term->swatch_value }}"></span>
                                            @endif
                                            {{ $term->name }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-[#94A3B8]">No terms yet.</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="w-full space-y-3 lg:w-[26rem]">
                                <form method="post" action="{{ route('catalog.attributes.update', $attribute) }}" class="grid gap-2 rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-3 sm:grid-cols-2">
                                    @csrf
                                    @method('PATCH')
                                    <input name="name" value="{{ $attribute->name }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                                    <select name="display_type" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                        @foreach (['text', 'select', 'color', 'number'] as $type)
                                            <option value="{{ $type }}" @selected($attribute->display_type === $type)>{{ ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                    <label class="inline-flex items-center gap-2 text-xs text-[#334155]"><input type="checkbox" name="is_filterable" value="1" @checked($attribute->is_filterable)> Filterable</label>
                                    <label class="inline-flex items-center gap-2 text-xs text-[#334155]"><input type="checkbox" name="is_visible" value="1" @checked($attribute->is_visible)> Visible</label>
                                    <button class="rounded-lg bg-[#0F172A] px-3 py-2 text-sm font-semibold text-white sm:col-span-2">Update attribute</button>
                                </form>
                                <form method="post" action="{{ route('catalog.attributes.terms.store', $attribute) }}" class="grid gap-2 rounded-xl border border-[#F1F5F9] bg-white p-3 sm:grid-cols-[1fr_7rem_auto]">
                                    @csrf
                                    <input name="name" placeholder="New term" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                                    <input name="swatch_value" placeholder="#0052CC" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                                    <button class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm font-semibold text-[#334155]">Add</button>
                                </form>
                                <form method="post" action="{{ route('catalog.attributes.destroy', $attribute) }}" onsubmit="return confirm('Remove this attribute from products in this store?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-sm font-semibold text-[#B42318] hover:underline">Remove attribute</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#CBD5E1] bg-white px-6 py-10 text-center text-sm text-[#64748B]">
                        No attributes yet. Add your first structured product fact above.
                    </div>
                @endforelse
            </section>
        </div>
    </div>
@endsection
