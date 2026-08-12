<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password — Merchant workspace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F5F7F8] text-[#0F172A]">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold">Set a new password</h1>
            <p class="mt-2 text-sm text-[#64748B]">Enter the email for your merchant account and choose a new password.</p>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B91C1C]">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="block space-y-1.5">
                    <span class="text-sm font-medium text-[#334155]">Email</span>
                    <input type="email" name="email" value="{{ old('email', $email) }}" required class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                </label>
                <label class="block space-y-1.5">
                    <span class="text-sm font-medium text-[#334155]">New password</span>
                    <div class="relative">
                        <input id="reset_password" type="password" name="password" required class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 pr-12 text-sm">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-[#64748B]" data-password-toggle="reset_password" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                </label>
                <label class="block space-y-1.5">
                    <span class="text-sm font-medium text-[#334155]">Confirm password</span>
                    <div class="relative">
                        <input id="reset_password_confirmation" type="password" name="password_confirmation" required class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 pr-12 text-sm">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-[#64748B]" data-password-toggle="reset_password_confirmation" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                </label>
                <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover">Update password</button>
            </form>
        </div>
    </main>
</body>
</html>
