@extends('layouts.admin.admin-Sidebar')

@section('title', 'Admin dashboard — '.config('app.name'))

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-stone-900">Platform overview</h1>
            <p class="mt-1 text-sm text-stone-600">Store access and entitlement counts from live subscription records. SaaS payment collection is not enabled yet.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Total stores</p>
                <p class="mt-2 text-3xl font-semibold text-stone-900">{{ number_format($totalStores) }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Active trials</p>
                <p class="mt-2 text-3xl font-semibold text-stone-900">{{ number_format($activeTrials) }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Expired access</p>
                <p class="mt-2 text-3xl font-semibold text-stone-900">{{ number_format($expired) }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Ending in 7 days</p>
                <p class="mt-2 text-3xl font-semibold text-stone-900">{{ number_format($endingSoon) }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Trial or active</p>
                <p class="mt-2 text-2xl font-semibold text-stone-900">{{ number_format($activeAccess) }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Suspended</p>
                <p class="mt-2 text-2xl font-semibold text-stone-900">{{ number_format($suspended) }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">No entitlement row</p>
                <p class="mt-2 text-2xl font-semibold text-stone-900">{{ number_format($ungatedStores) }}</p>
                <p class="mt-1 text-xs text-stone-500">Stores without an assigned package or trial remain open until you set access.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin-tenant') }}" class="inline-flex items-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">Manage tenants</a>
            <a href="{{ route('admin-billing') }}" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-50">Manage packages</a>
        </div>
    </div>
@endsection
