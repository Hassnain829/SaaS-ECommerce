<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password — Merchant workspace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F5F7F8] text-[#0F172A]">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold">Forgot password</h1>
            <p class="mt-2 text-sm text-[#64748B]">Enter your account email and we will send reset instructions if that account exists.</p>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-4 py-3 text-sm text-[#166534]">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B91C1C]">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block space-y-1.5">
                    <span class="text-sm font-medium text-[#334155]">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                </label>
                <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover">Send reset link</button>
            </form>

            <p class="mt-6 text-sm text-[#64748B]">
                <a href="{{ route('signin') }}" class="font-semibold text-[#0052CC] hover:underline">Back to sign in</a>
            </p>
        </div>
    </main>
</body>
</html>
