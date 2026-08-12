<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify email — Merchant workspace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F5F7F8] text-[#0F172A]">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold">Verify your email</h1>
            <p class="mt-2 text-sm text-[#64748B]">
                We sent a verification link to <strong>{{ auth()->user()?->email }}</strong>. Open that link to confirm your address.
            </p>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-4 py-3 text-sm text-[#166534]">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover">Resend verification email</button>
            </form>

            <div class="mt-6 space-y-2 text-sm text-[#64748B]">
                @if (! auth()->user()?->memberStores()->exists())
                    <a href="{{ route('onboarding-StoreDetails-1') }}" class="block font-semibold text-[#0052CC] hover:underline">Continue store setup</a>
                @else
                    <a href="{{ route('dashboard') }}" class="block font-semibold text-[#0052CC] hover:underline">Continue to dashboard</a>
                @endif
                <p class="text-xs">You can keep working while verification is pending. Verify soon so account recovery emails reach the right inbox.</p>
            </div>
        </div>
    </main>
</body>
</html>
