@extends('layouts.admin.admin-Sidebar')

@section('title', 'Billing packages — '.config('app.name'))

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900">Packages &amp; pricing</h1>
                <p class="mt-1 text-sm text-stone-600">Admin-managed package catalog for store entitlements. Stripe SaaS charging is not live yet — prices are display and planning fields only.</p>
            </div>
            <a href="{{ route('admin-billing.packages.create') }}" class="inline-flex items-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">New package</a>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-4 py-3">Package</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Interval</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Stores</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($packages as $package)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-stone-900">{{ $package->name }}</div>
                                <div class="text-xs text-stone-500">{{ $package->slug }}</div>
                            </td>
                            <td class="px-4 py-3 text-stone-700">{{ $package->formattedPrice() }}</td>
                            <td class="px-4 py-3 text-stone-700">{{ $package->billingIntervalLabel() }}</td>
                            <td class="px-4 py-3">
                                @if ($package->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                @else
                                    <span class="inline-flex rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-semibold text-stone-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-stone-700">{{ number_format($package->subscriptions_count) }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('admin-billing.packages.edit', $package) }}" class="font-semibold text-brand hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin-billing.packages.toggle', $package) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="font-semibold text-stone-600 hover:underline">{{ $package->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-stone-500">No packages yet. Create one to assign to stores.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
