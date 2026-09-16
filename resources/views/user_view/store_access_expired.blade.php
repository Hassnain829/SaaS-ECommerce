<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store access expired — {{ config('app.name') }}</title>
    @include('partials.platform-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl flex-col justify-center px-6 py-16">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-sm">
            <a href="{{ route('signin') }}" class="mb-6 inline-flex" aria-label="Retailo home">
                <x-platform.logo class="h-8" />
            </a>
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-[#64748B]">Store access</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">Access to this store has ended</h1>
            <p class="mt-3 text-sm leading-relaxed text-[#475569]">
                Your merchant workspace for
                <span class="font-semibold text-stone-900">{{ $store?->name ?? 'this store' }}</span>
                is unavailable until a platform administrator extends access.
            </p>

            <dl class="mt-6 space-y-3 rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-stone-500">Status</dt>
                    <dd class="font-medium text-stone-900">{{ $subscription?->statusLabel() ?? 'Unavailable' }}</dd>
                </div>
                @if ($subscription?->package)
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Package</dt>
                        <dd class="font-medium text-stone-900">{{ $subscription->package->name }}</dd>
                    </div>
                @endif
                @if ($subscription?->trial_ends_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Trial ended</dt>
                        <dd class="font-medium text-stone-900">{{ $subscription->trial_ends_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if ($subscription?->access_ends_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Access ended</dt>
                        <dd class="font-medium text-stone-900">{{ $subscription->access_ends_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
            </dl>

            <p class="mt-5 text-sm text-stone-600">Contact your platform administrator to renew or extend access. Self-serve upgrade is not available yet.</p>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('generalSettings', ['tab' => 'account']) }}" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-50">Profile settings</a>
                <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">Sign out</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
