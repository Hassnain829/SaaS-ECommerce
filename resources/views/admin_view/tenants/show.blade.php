@extends('layouts.admin.admin-Sidebar')

@section('title', $store->name.' — Tenants')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin-tenant') }}" class="text-sm font-medium text-brand hover:underline">← All tenants</a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-900">{{ $store->name }}</h1>
            <p class="mt-1 text-sm text-stone-600">Slug {{ $store->slug }} · Owner {{ $store->user?->email ?? '—' }}</p>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm lg:col-span-1">
                <h2 class="text-sm font-semibold text-stone-900">Current access</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-stone-500">Status</dt>
                        <dd class="mt-0.5 font-medium text-stone-900">{{ $subscription?->statusLabel() ?? 'Open (no entitlement row)' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Package</dt>
                        <dd class="mt-0.5 font-medium text-stone-900">{{ $subscription?->package?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Trial ends</dt>
                        <dd class="mt-0.5 font-medium text-stone-900">{{ $subscription?->trial_ends_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Access ends</dt>
                        <dd class="mt-0.5 font-medium text-stone-900">{{ $subscription?->access_ends_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Last assigned by</dt>
                        <dd class="mt-0.5 font-medium text-stone-900">{{ $subscription?->assignedBy?->email ?? '—' }}</dd>
                    </div>
                    @if ($subscription?->notes)
                        <div>
                            <dt class="text-stone-500">Notes</dt>
                            <dd class="mt-0.5 whitespace-pre-wrap text-stone-800">{{ $subscription->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="space-y-4 lg:col-span-2">
                <form method="POST" action="{{ route('admin-tenant.access', $store) }}" class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm space-y-4">
                    @csrf
                    <h2 class="text-sm font-semibold text-stone-900">Update access</h2>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500" for="action">Action</label>
                        <select name="action" id="action" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm" required>
                            <option value="grant_trial">Grant / extend trial</option>
                            <option value="assign_package">Assign package (active)</option>
                            <option value="suspend">Suspend access</option>
                            <option value="reactivate">Reactivate</option>
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500" for="package_id">Package</label>
                            <select name="package_id" id="package_id" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                                <option value="">No change / none</option>
                                @foreach ($packages as $package)
                                    <option value="{{ $package->id }}" @selected(old('package_id', $subscription?->package_id) == $package->id)>
                                        {{ $package->name }}{{ $package->is_active ? '' : ' (inactive)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500" for="trial_days">Trial days</label>
                            <input type="number" min="1" max="3650" name="trial_days" id="trial_days" value="{{ old('trial_days', 14) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-stone-500">Used for grant trial and optional reactivate with days. Extends from the later of now or current access end.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500" for="notes">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('notes', $subscription?->notes) }}</textarea>
                    </div>

                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">Save access changes</button>
                </form>
            </div>
        </div>
    </div>
@endsection
