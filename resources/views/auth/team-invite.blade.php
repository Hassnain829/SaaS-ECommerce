<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept invitation — {{ config('app.name') }}</title>
    @include('partials.platform-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F5F7F8] text-[#0F172A]">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-sm">
            <a href="{{ route('signin') }}" class="mb-6 inline-flex" aria-label="{{ config('app.name') }} home">
                <x-platform.logo class="h-8" />
            </a>

            @if ($expired && ! $alreadyAccepted)
                <h1 class="text-section font-semibold">This invitation link expired</h1>
                <p class="mt-2 text-sm text-[#64748B]">Ask the store owner to send a new invitation, then use the latest email.</p>
                <p class="mt-6 text-sm text-[#64748B]">
                    <a href="{{ route('signin') }}" class="font-semibold text-[#0052CC] hover:underline">Back to sign in</a>
                </p>
            @elseif ($alreadyAccepted)
                <h1 class="text-section font-semibold">Invitation already accepted</h1>
                <p class="mt-2 text-sm text-[#64748B]">
                    @if ($invitee)
                        This access is already active. Sign in with {{ $invitee->email }} to continue.
                    @else
                        This access is already active. Sign in to continue.
                    @endif
                </p>
                <a href="{{ route('signin') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover">Sign in</a>
            @elseif ($requiresSignIn)
                <h1 class="text-section font-semibold">Sign in to accept</h1>
                <p class="mt-2 text-sm text-[#64748B]">
                    Sign in as <strong>{{ $invitee->email }}</strong> to join
                    {{ $stores->count() === 1 ? $stores->first()->name : 'your stores' }}.
                    This invitation cannot sign you in by itself.
                </p>
                @if ($stores->isNotEmpty())
                    <ul class="mt-4 space-y-1 text-sm text-[#334155]">
                        @foreach ($stores as $store)
                            <li class="rounded-lg bg-[#F8FAFC] px-3 py-2 font-medium">{{ $store->name }}</li>
                        @endforeach
                    </ul>
                @endif
                <a
                    href="{{ route('signin') }}"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover"
                >Sign in to accept</a>
            @elseif ($signedInAsOther)
                <h1 class="text-section font-semibold">Wrong account</h1>
                <p class="mt-2 text-sm text-[#64748B]">
                    This invitation is for {{ $invitee->email }}. Sign out, then sign in with that email before accepting.
                </p>
                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover">
                        Sign out
                    </button>
                </form>
            @else
                <h1 class="text-section font-semibold">Join {{ $stores->count() === 1 ? $stores->first()->name : 'your stores' }}</h1>
                <p class="mt-2 text-sm text-[#64748B]">
                    {{ $invitee->name }}, accept this invitation for {{ $invitee->email }}
                    @if ($needsPassword)
                        and choose a password to finish setting up your account.
                    @else
                        to start using the store access that was prepared for you.
                    @endif
                </p>

                @if ($stores->isNotEmpty())
                    <ul class="mt-4 space-y-1 text-sm text-[#334155]">
                        @foreach ($stores as $store)
                            <li class="rounded-lg bg-[#F8FAFC] px-3 py-2 font-medium">{{ $store->name }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($errors->any())
                    <div class="mt-4 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B91C1C]">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ $acceptUrl }}" class="mt-6 space-y-4">
                    @csrf
                    @if ($needsPassword)
                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[#334155]">Password</span>
                            <div class="relative">
                                <input id="invite_password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 pr-12 text-sm">
                                <button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-[#64748B]" data-password-toggle="invite_password" aria-label="Show password" aria-pressed="false">Show</button>
                            </div>
                        </label>
                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[#334155]">Confirm password</span>
                            <div class="relative">
                                <input id="invite_password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 pr-12 text-sm">
                                <button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-[#64748B]" data-password-toggle="invite_password_confirmation" aria-label="Show password" aria-pressed="false">Show</button>
                            </div>
                        </label>
                    @endif
                    <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-hover">
                        {{ $needsPassword ? 'Set password and join' : 'Accept invitation' }}
                    </button>
                </form>
            @endif
        </div>
    </main>
</body>
</html>
