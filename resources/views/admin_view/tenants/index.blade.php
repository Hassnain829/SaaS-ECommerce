@extends('layouts.admin.admin-Sidebar')

@section('title', 'Tenants — '.config('app.name'))

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900">Tenants</h1>
                <p class="mt-1 text-sm text-stone-600">Assign packages, trials, and suspend or restore store access.</p>
            </div>
            <form method="GET" action="{{ route('admin-tenant') }}" class="flex gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search stores" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                <button type="submit" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-50">Search</button>
            </form>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-4 py-3">Store</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Package</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Access ends</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($stores as $store)
                        @php($sub = $store->subscription)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-stone-900">{{ $store->name }}</div>
                                <div class="text-xs text-stone-500">{{ $store->slug }}</div>
                            </td>
                            <td class="px-4 py-3 text-stone-700">{{ $store->user?->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-stone-700">{{ $sub?->package?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($sub)
                                    <span class="inline-flex rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-semibold text-stone-700">{{ $sub->statusLabel() }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-800">Open</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ $sub?->access_ends_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin-tenant.show', $store) }}" class="font-semibold text-brand hover:underline">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-stone-500">No stores found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $stores->links() }}
    </div>
@endsection
