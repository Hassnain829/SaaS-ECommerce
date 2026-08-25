<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm password — Merchant workspace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F5F7F8] text-[#0F172A]">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold">Confirm your password</h1>
            <p class="mt-2 text-sm text-[#64748B]">
                For your security, confirm your account password before continuing with this sensitive action.
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="ml-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-[#334155]">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:border-[#0052CC] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"
                    >
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover">Confirm and continue</button>
            </form>

            <div class="mt-6 text-sm">
                <a href="{{ route('store-management') }}" class="font-semibold text-[#0052CC] hover:underline">Cancel and return to store management</a>
            </div>
        </div>
    </main>
</body>
</html>
