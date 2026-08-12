<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service — Merchant workspace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F5F7F8] text-[#0F172A]">
    <main class="mx-auto max-w-3xl px-4 py-12">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('register') }}" class="text-sm font-semibold text-[#0052CC] hover:underline">Back</a>
        <h1 class="mt-4 text-3xl font-semibold">Terms of Service</h1>
        <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
            Draft placeholder only. Legal review is incomplete — this page is not final counsel-approved Terms.
        </div>
        <div class="prose prose-slate mt-8 max-w-none text-sm leading-7 text-[#334155]">
            <p>These Terms describe how merchants may use this merchant operations workspace to manage catalog, orders, customers, delivery setup, and related store configuration.</p>
            <p>You are responsible for the accuracy of store data you enter, for complying with applicable law in your markets, and for carrier or payment relationships you connect under your own accounts.</p>
            <p>The platform provides connectivity and operations tooling. It does not claim to act as your legal counsel, postage payer, or certified compliance authority.</p>
            <p>We may update these Terms as the product matures. Continued use after an update means you accept the revised Terms once they are published here.</p>
        </div>
    </main>
</body>
</html>
