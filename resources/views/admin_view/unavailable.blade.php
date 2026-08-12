<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageLabel }} — Platform admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl flex-col justify-center px-6 py-16">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-[#64748B]">Platform admin</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $pageLabel }} is not available yet</h1>
            <p class="mt-3 text-sm leading-relaxed text-[#475569]">
                This page previously showed placeholder metrics. Invented platform data has been removed until real operator tools are ready.
            </p>
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">
                Back to app
            </a>
        </div>
    </main>
</body>
</html>
