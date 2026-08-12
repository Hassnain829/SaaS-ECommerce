<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — Merchant workspace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen bg-[#F5F7F8] text-[#0F172A]">
    <main class="mx-auto max-w-3xl px-4 py-12">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('register') }}" class="text-sm font-semibold text-[#0052CC] hover:underline">Back</a>
        <h1 class="mt-4 text-3xl font-semibold">Privacy Policy</h1>
        <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
            Draft placeholder only. Legal review is incomplete — this page is not a final counsel-approved privacy notice.
        </div>
        <div class="prose prose-slate mt-8 max-w-none text-sm leading-7 text-[#334155]">
            <p>This workspace stores account details you provide (such as name and email), store configuration, catalog and order data, and operational logs needed to run merchant features.</p>
            <p>We use this information to authenticate users, operate store-scoped features, send account emails such as password reset and verification, and improve reliability and security.</p>
            <p>We do not invent certifications, carrier approvals, or data-processing claims beyond what the product actually performs. Connected carrier or payment providers process data under their own agreements when you authorize those connections.</p>
            <p>Contact the platform operator through your normal support channel if you need account corrections or deletion requests.</p>
        </div>
    </main>
</body>
</html>
