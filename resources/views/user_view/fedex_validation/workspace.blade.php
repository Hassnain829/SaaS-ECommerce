@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Validation Workspace | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="FedEx approval tools" lead="Validation scenarios and evidence for carrier approval.">
    </x-ui.merchant-topbar>
@endsection

@section('content')
    @php
        $ready = (bool) ($preflight['ready'] ?? false);
        $blockers = $preflight['blockers'] ?? [];
        $statusBadge = static function (string $status): string {
            return match ($status) {
                'passed' => 'bg-emerald-50 text-emerald-800',
                'blocked' => 'bg-amber-50 text-amber-900',
                'failed', 'invalid' => 'bg-red-50 text-red-800',
                default => 'bg-slate-100 text-slate-700',
            };
        };
        $checkStatus = fn (string $key): string => (string) (($checksByKey[$key]['status'] ?? 'not_tested'));
    @endphp

    <div class="ui-page-enter mx-auto max-w-[1100px] space-y-6">
        <x-ui.operator-banner title="Certification tools — not required for day-to-day shipping setup">
            Use this workspace only when preparing a FedEx approval package. Everyday delivery setup lives under Delivery.
        </x-ui.operator-banner>

        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <section class="rounded-2xl border border-[color:var(--color-border)] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[color:var(--color-ink-muted)]">Approval checklist</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[color:var(--color-ink)]">
                        {{ $preflight['completed_count'] ?? 0 }} of {{ $preflight['total_count'] ?? 0 }} required checks complete
                    </h2>
                    <p class="mt-2 text-sm text-[color:var(--color-ink-muted)]">
                        @if ($ready)
                            Final submission export is available when you are ready to send the package.
                        @else
                            Complete the remaining evidence below before requesting the final FedEx validation package.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.export.diagnostic', $account) }}" class="inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-4 py-2 text-sm font-semibold text-[#475569]">Export diagnostic bundle</a>
                    @if ($ready)
                        <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.export.final', $account) }}" class="inline-flex items-center rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Export final FedEx package</a>
                    @else
                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500" title="Final export stays disabled until preflight passes.">Final export blocked</span>
                    @endif
                </div>
            </div>

            @unless ($ready)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-950">INCOMPLETE — NOT READY FOR FEDEX SUBMISSION</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900">
                        @foreach ($blockers as $blocker)
                            <li>{{ $blocker['label'] ?? 'Missing evidence' }} — {{ $blocker['explanation'] ?? 'Complete this check.' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endunless
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Package 8 - Final submission</h3>
            <p class="mt-1 text-sm text-[#64748B]">Branding, capability disclosure, immutable snapshot, and deterministic final ZIP.</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($finalReadinessGroups ?? [] as $group)
                    <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm">
                        <p class="font-semibold text-[#0F172A]">{{ $group['label'] ?? 'Group' }}</p>
                        <p class="mt-1 text-xs text-[#64748B]">{{ $group['passed'] ?? 0 }} / {{ $group['total'] ?? 0 }} · {{ str($group['status'] ?? 'incomplete')->replace('_', ' ') }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button variant="secondary" :href="route('settings.shipping.carrier-accounts.fedex.capabilities', $account)">Open capabilities page</x-ui.button>
                <x-ui.button variant="secondary" :href="route('settings.shipping.carrier-accounts.fedex.capabilities', [$account, 'evidence_mode' => 1])">Open branding evidence page</x-ui.button>
            </div>

            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.final-preflight', $account) }}" class="mt-4">
                @csrf
                <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Run Final Submission Preflight</button>
            </form>

            @if ($finalPreflight['ready'] ?? false)
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.final-snapshot', $account) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="block space-y-1 text-sm">
                        <span class="font-semibold text-[#475569]">Case reference (optional)</span>
                        <input type="text" name="case_reference" placeholder="Americas" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 md:w-64">
                    </label>
                    <button type="submit" class="h-10 rounded-lg border border-[#0052CC] bg-white px-4 text-sm font-bold text-[#0052CC]">Create Final Submission Snapshot</button>
                </form>
            @endif

            @if ($latestFinalSnapshot ?? null)
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.final-export', [$account, $latestFinalSnapshot]) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="h-10 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white">Export Final FedEx Package (snapshot #{{ $latestFinalSnapshot->id }})</button>
                </form>
            @endif

            <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <p class="text-sm font-semibold text-[#0F172A]">Branding screenshots</p>
                <p class="mt-1 text-xs text-[#64748B]">Logo approved: {{ ($brandComplianceStatus['logo_approved_source'] ?? false) ? 'yes' : 'no — supply official FedEx asset first' }}</p>
                @foreach ([
                    \App\Models\FedExValidationArtifact::TYPE_FEDEX_BRANDING_UI_SCREENSHOT => 'Branding and legal notice',
                    \App\Models\FedExValidationArtifact::TYPE_FEDEX_SERVICES_PACKAGING_SCREENSHOT => 'Services and packaging',
                    \App\Models\FedExValidationArtifact::TYPE_FEDEX_SPECIAL_HANDLING_SCREENSHOT => 'Special handling',
                ] as $type => $label)
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.branding-screenshots.upload', $account) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="screenshot_type" value="{{ $type }}">
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">{{ $label }}</span>
                            <input type="file" name="screenshot" accept=".pdf,.png,.jpg,.jpeg" required class="block w-full text-sm">
                        </label>
                        <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Upload</button>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Hosted FedEx EULA</h3>
            <p class="mt-1 text-sm text-[#64748B]">Official FedEx hosted third-party End User License Agreement review, acceptance, and screenshot evidence.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['label' => 'Official document', 'value' => ($hostedEulaStatus['document_valid'] ?? false) ? 'Valid' : 'Invalid'],
                    ['label' => 'Document version', 'value' => $hostedEulaStatus['document_version'] ?? '—'],
                    ['label' => 'Full agreement viewed', 'value' => $hostedEulaStatus['full_agreement_viewed'] ?? 'Incomplete'],
                    ['label' => 'Read acknowledgement', 'value' => $hostedEulaStatus['read_acknowledgement'] ?? 'Missing'],
                    ['label' => 'Current document acceptance', 'value' => $hostedEulaStatus['acceptance_status'] ?? 'Missing'],
                    ['label' => 'Evidence screenshots', 'value' => $hostedEulaStatus['evidence_screenshots'] ?? 'Missing'],
                ] as $row)
                    <div class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                        <span class="text-sm font-semibold text-[#0F172A]">{{ $row['label'] }}</span>
                        <span class="text-sm text-[#475569]">{{ $row['value'] }}</span>
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.eula-review', $account) }}" class="mt-4">
                @csrf
                <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Review and accept Hosted EULA</button>
            </form>
            @if ($hostedEulaStatus['upload_allowed'] ?? false)
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.eula-evidence.upload', $account) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <label class="block text-sm">
                        <span class="font-semibold text-[#475569]">Full EULA UI evidence PDF</span>
                        <input type="file" name="full_ui_evidence" accept="application/pdf" required class="mt-1 block w-full text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="font-semibold text-[#475569]">Acceptance confirmation screenshot</span>
                        <input type="file" name="acceptance_confirmation" accept="application/pdf,image/png,image/jpeg" required class="mt-1 block w-full text-sm">
                    </label>
                    <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white md:col-span-2 md:w-fit">Upload EULA Evidence</button>
                </form>
            @else
                <p class="mt-3 text-xs text-[#64748B]">Accept the current official hosted EULA before uploading screenshot evidence.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Authorization</h3>
            <p class="mt-1 text-sm text-[#64748B]">Fresh parent and child OAuth transactions for FedEx integrator validation evidence.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ([
                    'authorization_parent' => 'Parent authorization',
                    'authorization_child' => 'Child authorization',
                ] as $authKey => $authLabel)
                    @php($authCheck = $checksByKey->get($authKey))
                    <div class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                        <span class="text-sm font-semibold text-[#0F172A]">{{ $authLabel }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge((string) ($authCheck['status'] ?? 'not_tested')) }}">{{ str((string) ($authCheck['status'] ?? 'not_tested'))->replace('_', ' ')->title() }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-[#64748B]">Uses stored sandbox platform and child credentials. A fresh OAuth transaction is generated for each required authorization check.</p>
            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.authorization', $account) }}" class="mt-4" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                @csrf
                <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">Run Parent + Child Authorization</button>
            </form>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Sweden MFA Passthrough</h3>
            <p class="mt-1 text-sm text-[#64748B]">Uses the locked FedEx Sweden passthrough workbook fixture. PIN, SMS, email, call and invoice verification are not requested.</p>
            <div class="mt-4 grid gap-2 text-sm">
                <div class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                    <span class="font-semibold text-[#475569]">Workbook account</span>
                    <span class="font-semibold text-[#0F172A]">****{{ $swedenAccountLast4 ?? '9268' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                    <span class="font-semibold text-[#475569]">Country</span>
                    <span class="font-semibold text-[#0F172A]">Sweden</span>
                </div>
                @foreach ([
                    'address_validation' => 'Registration address validation',
                    'child_credentials_returned' => 'Child credentials returned',
                    'mfa_challenge' => 'MFA challenge',
                    'direct_child_authorization' => 'Direct child authorization',
                    'screenshots' => 'Screenshots',
                ] as $statusKey => $statusLabel)
                    <div class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                        <span class="font-semibold text-[#475569]">{{ $statusLabel }}</span>
                        <span class="font-semibold text-[#0F172A]">{{ $swedenPassthroughStatus[$statusKey] ?? 'Not tested' }}</span>
                    </div>
                @endforeach
            </div>
            @unless ($swedenPassthroughAvailable ?? false)
                <p class="mt-3 text-sm text-amber-800">Sweden passthrough fixture is not configured. Set the FedEx baseline workbook or Sweden validation environment values before running this check.</p>
            @else
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.sweden-passthrough', $account) }}" class="mt-4" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                    @csrf
                    <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">Run Sweden MFA Passthrough</button>
                </form>
            @endunless
            @if ($swedenScreenshotsUploadAllowed ?? false)
                <div class="mt-6 border-t border-[#E2E8F0] pt-4">
                    <p class="text-sm font-semibold text-[#0F172A]">Sweden passthrough screenshots</p>
                    <p class="mt-1 text-xs text-[#64748B]">Upload screenshots from the sanitized validation workspace only. Do not upload pages containing credentials or access tokens.</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.sweden-screenshots.upload', $account) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
                        @csrf
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Address/passthrough result screenshot</span>
                            <input type="file" name="address_screenshot" accept="application/pdf,image/png,image/jpeg" required class="block w-full text-sm">
                        </label>
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Direct child authorization screenshot</span>
                            <input type="file" name="child_authorization_screenshot" accept="application/pdf,image/png,image/jpeg" required class="block w-full text-sm">
                        </label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white md:col-span-2 md:w-fit">Upload Sweden Screenshots</button>
                    </form>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Required validation documents</h3>
            <p class="mt-1 text-sm text-[#64748B]">Upload the cover sheet, product worksheet, and customer-facing screenshots PDF.</p>
            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.documents.upload', $account) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                <label class="block space-y-1 text-sm">
                    <span class="font-semibold text-[#475569]">Document type</span>
                    <select name="document_type" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3">
                        <option value="{{ \App\Models\FedExValidationArtifact::DOC_COVER_SHEET }}">Integrator Validation Cover Sheet</option>
                        <option value="{{ \App\Models\FedExValidationArtifact::DOC_PIW }}">Product Information Worksheet</option>
                        <option value="{{ \App\Models\FedExValidationArtifact::DOC_CUSTOMER_SCREENSHOTS }}">Customer-facing screenshots PDF</option>
                    </select>
                </label>
                <label class="block space-y-1 text-sm md:col-span-2">
                    <span class="font-semibold text-[#475569]">PDF file</span>
                    <input type="file" name="document" accept="application/pdf" required class="block w-full text-sm">
                </label>
                <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white md:col-span-3 md:w-fit">Upload document</button>
            </form>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Registration / MFA evidence</h3>
            <p class="mt-1 text-sm text-[#64748B]">Run additional registration MFA checks on this connected account without starting a new FedEx connection.</p>
            <article class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-[#0F172A]">Registration address / account validation</p>
                        <p class="mt-1 text-xs text-[#64748B]">Re-runs the linked FedEx registration address step. A successful result includes FedEx MFA options — that is expected and counts as passed evidence.</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus('registration_address_validation')) }}">{{ str($checkStatus('registration_address_validation'))->replace('_', ' ')->title() }}</span>
                </div>
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.mfa.registration-address', $account) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Run Registration Address Validation</button>
                </form>
            </article>
            <article class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-[#0F172A]">Invoice validation</p>
                        <p class="mt-1 text-xs text-[#64748B]">FedEx sandbox invoice MFA workbook step — uses your linked registration session.</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus('registration_invoice_validation')) }}">{{ str($checkStatus('registration_invoice_validation'))->replace('_', ' ')->title() }}</span>
                </div>
                @unless ($invoiceEndpointConfigured ?? false)
                    <p class="mt-3 text-sm text-amber-800">Not configured — set <code class="text-xs">FEDEX_MFA_INVOICE_VALIDATION_PATH</code> before running this check.</p>
                @else
                    <p class="mt-3 text-xs text-[#64748B]">Sandbox defaults from the FedEx integrator workbook (account ending <strong>{{ $sandboxAccountEnding ?? '****' }}</strong>). Invoice date must be within the last 6 months. A fresh FedEx authorization token is fetched automatically before each run.</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.mfa.invoice', $account) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Invoice number</span>
                            <input type="text" name="invoice_number" value="{{ old('invoice_number', $mfaInvoicePrefill['number'] ?? '') }}" required maxlength="64" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        </label>
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Invoice date</span>
                            <input type="date" name="invoice_date" value="{{ old('invoice_date', $mfaInvoicePrefill['date'] ?? '') }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        </label>
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Currency</span>
                            <input type="text" name="invoice_currency" value="{{ old('invoice_currency', $mfaInvoicePrefill['currency'] ?? 'USD') }}" maxlength="3" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase">
                        </label>
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Amount</span>
                            <input type="text" name="invoice_amount" value="{{ old('invoice_amount', $mfaInvoicePrefill['amount'] ?? '') }}" required maxlength="32" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        </label>
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Run invoice validation</button>
                        </div>
                    </form>
                @endunless
            </article>
            @foreach ([
                [
                    'method' => 'email',
                    'title' => 'Email PIN',
                    'generation_key' => 'registration_pin_generation_email',
                    'validation_key' => 'registration_pin_validation_email',
                    'hint' => 'FedEx sends a secure code to the email on your FedEx account registration.',
                ],
                [
                    'method' => 'call',
                    'title' => 'Phone-call PIN',
                    'generation_key' => 'registration_pin_generation_call',
                    'validation_key' => 'registration_pin_validation_call',
                    'hint' => 'FedEx calls the phone number on your FedEx account registration with a secure code.',
                ],
            ] as $pinCard)
                <article class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-[#0F172A]">{{ $pinCard['title'] }}</p>
                            <p class="mt-1 text-xs text-[#64748B]">{{ $pinCard['hint'] }} Run generation first, then enter the code you receive. A fresh FedEx authorization token is fetched automatically when needed.</p>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus($pinCard['generation_key'])) }}">Gen: {{ str($checkStatus($pinCard['generation_key']))->replace('_', ' ')->title() }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus($pinCard['validation_key'])) }}">Validate: {{ str($checkStatus($pinCard['validation_key']))->replace('_', ' ')->title() }}</span>
                        </div>
                    </div>
                    @unless (($pinGenerationEndpointConfigured ?? false) && ($pinValidationEndpointConfigured ?? false))
                        <p class="mt-3 text-sm text-amber-800">Not configured — set <code class="text-xs">FEDEX_MFA_PIN_GENERATION_PATH</code> and <code class="text-xs">FEDEX_MFA_PIN_VALIDATION_PATH</code> before running these checks.</p>
                    @else
                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.mfa.pin.generate', [$account, $pinCard['method']]) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Send {{ strtolower($pinCard['title']) }}</button>
                        </form>
                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.mfa.pin.validate', [$account, $pinCard['method']]) }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <label class="block min-w-[12rem] flex-1 space-y-1 text-sm">
                                <span class="font-semibold text-[#475569]">Secure code</span>
                                <input type="text" name="pin" inputmode="numeric" autocomplete="one-time-code" required minlength="4" maxlength="12" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm tracking-widest" placeholder="Enter PIN">
                            </label>
                            <button type="submit" class="rounded-lg border border-[#0052CC] bg-white px-4 py-2 text-sm font-bold text-[#0052CC]">Validate {{ strtolower($pinCard['title']) }}</button>
                        </form>
                    @endunless
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Baseline API runs</h3>
            <p class="mt-1 text-sm text-[#64748B]">Run locked baseline checks from this workspace. Results are recorded as canonical validation evidence.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['key' => 'address_validation', 'label' => 'Address Validation', 'route' => 'settings.shipping.carrier-accounts.fedex.validation.run.address', 'baseline' => 'IntegratorUS02 recipient baseline'],
                    ['key' => 'service_availability', 'label' => 'Service Availability', 'route' => 'settings.shipping.carrier-accounts.fedex.validation.run.service-availability', 'baseline' => 'Default origin to IntegratorUS02 destination'],
                ] as $card)
                    <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-[#0F172A]">{{ $card['label'] }}</p>
                                <p class="mt-1 text-xs text-[#64748B]">{{ $card['baseline'] }}</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus($card['key'])) }}">{{ str($checkStatus($card['key']))->replace('_', ' ')->title() }}</span>
                        </div>
                        <form method="POST" action="{{ route($card['route'], $account) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white">Run {{ $card['label'] }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        @php($comprehensiveRate = $comprehensiveRateStatus ?? [])
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Comprehensive Rates &amp; Transit Times</h3>
            <p class="mt-1 text-sm text-[#64748B]">One-click locked baseline quote on the required FedEx Comprehensive Rates endpoint.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Endpoint</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $comprehensiveRate['endpoint'] ?? '/rate/v1/comprehensiverates/quotes' }}</p>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Fixture</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F172A]">Locked FedEx baseline (IntegratorUS02 package)</p>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">HTTP transaction</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ str($comprehensiveRate['transaction_status'] ?? 'not_tested')->replace('_', ' ')->title() }}</p>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">UI/response match</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ ($comprehensiveRate['ui_matches_response'] ?? false) ? 'Passed' : (($comprehensiveRate['transaction_status'] ?? '') === 'passed' ? 'Failed' : 'Not tested') }}</p>
                </div>
            </div>

            @if (($comprehensiveRate['transaction_status'] ?? '') === 'blocked')
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Comprehensive Rates access is blocked by FedEx.</p>
                    <p class="mt-2">The request was sent to the required Comprehensive Rates endpoint. Review the sanitized response evidence and contact the FedEx validation or project-access team.</p>
                    @if (! empty($comprehensiveRate['event']))
                        <ul class="mt-3 space-y-1 text-xs">
                            <li>HTTP status: {{ $comprehensiveRate['event']->http_status ?? '403' }}</li>
                            <li>FedEx error: {{ data_get($comprehensiveRate['event']->response_summary, 'fedex_error_code', '—') }}</li>
                            <li>Event ID: {{ $comprehensiveRate['event']->id }}</li>
                        </ul>
                    @endif
                </div>
            @elseif (($comprehensiveRate['transaction_status'] ?? '') === 'passed')
                <div class="mt-4 rounded-xl border border-[#BBF7D0] bg-[#F0FDF4] p-4">
                    <p class="text-sm font-semibold text-[#166534]">Customer rate result</p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ $comprehensiveRate['display_currency'] ?? 'USD' }} {{ $comprehensiveRate['display_amount'] ?? '—' }}</p>
                    <p class="mt-1 text-sm text-[#475569]">{{ $comprehensiveRate['display_service_type'] ?? 'Service' }} · {{ $comprehensiveRate['display_rate_type'] ?? 'ACCOUNT' }} rate</p>
                    @if (! empty($comprehensiveRate['available_rates']))
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="text-[#64748B]">
                                    <tr>
                                        <th class="py-1 pr-3">Service</th>
                                        <th class="py-1 pr-3">Rate type</th>
                                        <th class="py-1">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($comprehensiveRate['available_rates'] as $rateOption)
                                        <tr class="border-t border-[#DCFCE7] {{ ($rateOption['service_type'] ?? null) === ($comprehensiveRate['display_service_type'] ?? null) && ($rateOption['rate_type'] ?? null) === ($comprehensiveRate['display_rate_type'] ?? null) ? 'font-bold' : '' }}">
                                            <td class="py-2 pr-3">{{ $rateOption['service_name'] ?? $rateOption['service_type'] ?? 'Service' }}</td>
                                            <td class="py-2 pr-3">{{ $rateOption['rate_type'] ?? '—' }}</td>
                                            <td class="py-2">{{ $rateOption['currency'] ?? 'USD' }} {{ $rateOption['amount'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.comprehensive-rate', $account) }}" class="mt-4" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                @csrf
                <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">Run Comprehensive Rate Quote</button>
            </form>

            @if (! empty($comprehensiveRate['canonical_event']))
                <div class="mt-6 border-t border-[#E2E8F0] pt-4">
                    <p class="text-sm font-semibold text-[#0F172A]">Comprehensive rate result screenshot</p>
                    <p class="mt-1 text-xs text-[#64748B]">Upload a screenshot of the customer-facing rate result panel above. PNG, JPG, or PDF only.</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.comprehensive-rate-screenshot.upload', $account) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Screenshot file</span>
                            <input type="file" name="screenshot" accept="application/pdf,image/png,image/jpeg" required class="block w-full text-sm">
                        </label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Upload Rate Screenshot</button>
                    </form>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Locked US Ship Transactions & Labels</h3>
            <p class="mt-1 text-sm text-[#64748B]">Package 6 — one-click fresh label generation per workbook case. Print each generated label, scan at 600+ DPI, then upload the physical scan — never the raw API file.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                @foreach ($lockedShipScenarios as $testCaseKey => $meta)
                    @if (in_array($testCaseKey, ['IntegratorUS09_IMAGE', 'IntegratorUS09_DOCUMENT'], true))
                        @continue
                    @endif
                    @php($scenarioKey = $meta['scenario_key'])
                    @php($shipStatus = $lockedShipStatuses[$testCaseKey] ?? [])
                    @php($us08Excluded = $testCaseKey === 'IntegratorUS08' && ! ($us08ValidationEnabled ?? false))
                    <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">{{ $testCaseKey }}</p>
                                <p class="mt-1 font-semibold text-[#0F172A]">{{ $shipStatus['label'] ?? ($meta['label_format'].' / '.$meta['label_stock_type']) }}</p>
                                @if ($us08Excluded)
                                    <p class="mt-1 text-sm text-[#64748B]">Excluded from active validation</p>
                                @else
                                    <p class="mt-1 text-sm text-[#64748B]">{{ $meta['expected_packages'] }} package(s)</p>
                                @endif
                            </div>
                            @if ($us08Excluded)
                                <span class="rounded-full bg-[#F1F5F9] px-2 py-0.5 text-xs font-bold text-[#64748B]">Excluded</span>
                            @else
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus($scenarioKey.'_event')) }}">{{ str($checkStatus($scenarioKey.'_event'))->replace('_', ' ')->title() }}</span>
                            @endif
                        </div>

                        @if ($us08Excluded)
                            <div class="mt-3 rounded-lg border border-[#E2E8F0] bg-white p-3 text-xs text-[#475569]">
                                <p class="font-bold text-[#0F172A]">Freight LTL not in scope</p>
                                <p class="mt-1">{{ $us08ExclusionNote ?? 'IntegratorUS08 Freight LTL is excluded because Freight LTL is not a supported capability of this application and is no longer available through the current FedEx Developer Portal project.' }}</p>
                            </div>
                        @else
                            <dl class="mt-3 space-y-1 text-xs text-[#475569]">
                                <div class="flex justify-between gap-3"><dt>API transaction</dt><dd class="font-semibold">{{ str($shipStatus['transaction_status'] ?? 'not_tested')->replace('_', ' ')->title() }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Expected service</dt><dd class="font-semibold">{{ $shipStatus['expected_service_type'] ?? '—' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Response service</dt><dd class="font-semibold">{{ $shipStatus['response_service_type'] ?? '—' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Service match</dt><dd class="font-semibold">{{ str($shipStatus['service_match_status'] ?? 'not_tested')->replace('_', ' ')->title() }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Generated labels</dt><dd class="font-semibold">{{ $shipStatus['generated_labels'] ?? '0 of '.$meta['expected_packages'] }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Printed scans</dt><dd class="font-semibold">{{ $shipStatus['printed_scans'] ?? '0 of '.$meta['expected_packages'] }}</dd></div>
                                @if ($testCaseKey === 'IntegratorUS05')
                                    <div class="flex justify-between gap-3"><dt>MPS correlation</dt><dd class="font-semibold">{{ str($shipStatus['mps_correlation_status'] ?? 'not_tested')->replace('_', ' ')->title() }}</dd></div>
                                @endif
                            </dl>

                            @if ($testCaseKey === 'IntegratorUS08')
                                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
                                    <p class="font-bold">Freight LTL one-click run</p>
                                    <p class="mt-1">Runs local preflight, then a single sandbox POST to /ship/v1/freight/shipments. Persists the handling-unit ZPLII label, Straight Bill of Lading, and Commercial Invoice when FedEx returns them. Does not retry after authorization failures.</p>
                                </div>
                                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account) }}" class="mt-3 space-y-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                                    @csrf
                                    <label class="flex items-start gap-2 text-xs text-[#475569]">
                                        <input type="checkbox" name="confirm_freight_creation" value="1" required class="mt-0.5 rounded border-[#CBD5E1]">
                                        <span>I understand this creates one sandbox Freight shipment when local preflight passes.</span>
                                    </label>
                                    <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white">Run IntegratorUS08 Freight LTL</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [$account, 'testCaseKey' => $testCaseKey]) }}" class="mt-3" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white">Generate Fresh {{ $testCaseKey }} Label{{ $meta['expected_packages'] > 1 ? 's' : '' }}</button>
                                </form>
                            @endif

                            <p class="mt-2 text-xs text-[#64748B]">{{ $shipStatus['printing_instructions'] ?? 'Print the downloaded label before scanning.' }}</p>

                            <div class="mt-3 space-y-1">
                                @for ($i = 1; $i <= (int) $meta['expected_packages']; $i++)
                                    @php($labelCheck = $checksByKey->get($scenarioKey.'_label_'.$i))
                                    @if (! empty($labelCheck['artifact_id']))
                                        <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.artifacts.download', [$account, $labelCheck['artifact_id']]) }}" class="inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#0052CC] hover:bg-[#EFF6FF]">
                                            Download generated label — package {{ $i }}
                                        </a>
                                    @else
                                        <p class="text-xs text-[#94A3B8]">Package {{ $i }} label — run the scenario first</p>
                                    @endif
                                @endfor
                            </div>

                            @if (! empty($shipStatus['generated_label_artifacts']))
                                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
                                    <p class="font-bold">Printed scan workflow (required by FedEx)</p>
                                    <ol class="mt-2 list-decimal space-y-1 pl-4">
                                        <li>Download the generated label for this package.</li>
                                        <li>Print it on the correct stock (laser printer, actual size, no scaling).</li>
                                        <li>Scan the <strong>printed paper</strong> at 600 DPI or higher (PNG/JPG recommended).</li>
                                        <li>Upload that scan file here — <strong>not</strong> the downloaded API label.</li>
                                    </ol>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.scans.upload', $account) }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                                @csrf
                                <input type="hidden" name="test_case_key" value="{{ $testCaseKey }}">
                                <label class="block text-xs font-semibold text-[#475569]">Package sequence</label>
                                <select name="package_sequence" class="h-9 w-full rounded-lg border border-[#CBD5E1] px-2 text-sm">
                                    @for ($i = 1; $i <= (int) $meta['expected_packages']; $i++)
                                        <option value="{{ $i }}">Package {{ $i }}</option>
                                    @endfor
                                </select>
                                <label class="block text-xs font-semibold text-[#475569]">Scan DPI (minimum 600)</label>
                                <input type="number" name="scan_dpi" min="600" max="2400" value="600" required class="h-9 w-full rounded-lg border border-[#CBD5E1] px-2 text-sm">
                                <label class="block text-xs font-semibold text-[#475569]">Printed scan (PDF, PNG, or JPG)</label>
                                <p class="text-xs text-[#B45309]">Do not upload the downloaded API label file. Print the label first, then scan the physical print at 600 DPI or higher.</p>
                                <input type="file" name="scan" accept="application/pdf,image/png,image/jpeg" required class="block w-full text-xs">
                                <label class="flex items-start gap-2 text-xs text-[#475569]">
                                    <input type="checkbox" name="printed_scan_attestation" value="1" required class="mt-0.5">
                                    <span>I confirm that this file was created by physically printing the generated FedEx label and scanning the printed label at 600 DPI or higher without scaling.</span>
                                </label>
                                <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]">Upload printed scan</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">IntegratorUS09 — ETD Image</h3>
            <p class="mt-1 text-sm text-[#64748B]">Upload letterhead (IMAGE_1) and signature (IMAGE_2), create the international ETD PDF shipment, then print/scan the label. Do not use the generic parcel ship button.</p>
            <div class="mt-3 rounded-lg border border-[#DBEAFE] bg-[#EFF6FF] p-3 text-xs text-[#1E3A8A]">
                <p class="font-bold">Next step</p>
                <p class="mt-1">{{ $us09Status['image_next_action'] ?? 'Follow the numbered steps below.' }}</p>
            </div>
            @if ((! ($us09Status['assets']['letterhead'] ?? false) || ! ($us09Status['assets']['signature'] ?? false))
                && ($us09Status['image_ship']['transaction_status'] ?? null) !== 'passed')
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
                    Workbook assets are missing under <code>resources/fedex-validation/us09/</code>. Place real <code>signature3.png</code> and <code>signature2.png</code> before the final evidence run.
                </div>
            @endif
            <ol class="mt-4 list-decimal space-y-4 pl-5 text-sm text-[#475569]">
                <li>
                    <p class="font-semibold text-[#0F172A]">Upload letterhead image</p>
                    <p class="text-xs">Status: {{ str($us09Status['letterhead_check'] ?? 'not_tested')->replace('_', ' ')->title() }}</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.letterhead', $account) }}" class="mt-2 space-y-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                        @csrf
                        <label class="flex items-start gap-2 text-xs">
                            <input type="checkbox" name="confirm_us09_upload" value="1" required class="mt-0.5">
                            <span>I understand this uploads a sandbox letterhead image.</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white" @disabled(! ($us09Status['assets']['letterhead'] ?? false))>Upload letterhead</button>
                    </form>
                </li>
                <li>
                    <p class="font-semibold text-[#0F172A]">Upload signature image</p>
                    <p class="text-xs">Status: {{ str($us09Status['signature_check'] ?? 'not_tested')->replace('_', ' ')->title() }}</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.signature', $account) }}" class="mt-2 space-y-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                        @csrf
                        <label class="flex items-start gap-2 text-xs">
                            <input type="checkbox" name="confirm_us09_upload" value="1" required class="mt-0.5">
                            <span>I understand this uploads a sandbox signature image.</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white" @disabled(! ($us09Status['assets']['signature'] ?? false))>Upload signature</button>
                    </form>
                </li>
                <li>
                    <p class="font-semibold text-[#0F172A]">Create image ETD shipment</p>
                    <p class="text-xs">Ship status: {{ str(($us09Status['image_ship']['transaction_status'] ?? 'not_tested'))->replace('_', ' ')->title() }}</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.image', $account) }}" class="mt-2 space-y-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                        @csrf
                        <label class="flex items-start gap-2 text-xs">
                            <input type="checkbox" name="confirm_us09_ship" value="1" required class="mt-0.5">
                            <span>I understand this creates a sandbox international ETD shipment.</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white" @disabled(! ($us09Status['image_ship_ready'] ?? false))>Create IntegratorUS09 Image Shipment</button>
                    </form>
                </li>
            </ol>
            @include('user_view.fedex_validation.partials.printed_scan_workflow', [
                'account' => $account,
                'testCaseKey' => 'IntegratorUS09_IMAGE',
                'shipStatus' => $us09Status['image_ship'] ?? [],
            ])
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">IntegratorUS09 — ETD Document</h3>
            <p class="mt-1 text-sm text-[#64748B]">Upload the commercial invoice PDF, create the document-mode ETD shipment, then print/scan the label. The returned document id is injected automatically — never paste ids into the workspace.</p>
            <div class="mt-3 rounded-lg border border-[#DBEAFE] bg-[#EFF6FF] p-3 text-xs text-[#1E3A8A]">
                <p class="font-bold">Next step</p>
                <p class="mt-1">{{ $us09Status['document_next_action'] ?? 'Follow the numbered steps below.' }}</p>
            </div>
            @if (! ($us09Status['assets']['document'] ?? false)
                && ($us09Status['document_ship']['transaction_status'] ?? null) !== 'passed')
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
                    Missing <code>resources/fedex-validation/us09/commercial_invoice.pdf</code>. Provide a real workbook commercial invoice before the final evidence run.
                </div>
            @endif
            <ol class="mt-4 list-decimal space-y-4 pl-5 text-sm text-[#475569]">
                <li>
                    <p class="font-semibold text-[#0F172A]">Upload commercial invoice</p>
                    <p class="text-xs">Status: {{ str($us09Status['document_check'] ?? 'not_tested')->replace('_', ' ')->title() }}</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.document', $account) }}" class="mt-2 space-y-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                        @csrf
                        <label class="flex items-start gap-2 text-xs">
                            <input type="checkbox" name="confirm_us09_upload" value="1" required class="mt-0.5">
                            <span>I understand this uploads a sandbox commercial invoice document.</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white" @disabled(! ($us09Status['assets']['document'] ?? false))>Upload commercial invoice</button>
                    </form>
                </li>
                <li>
                    <p class="font-semibold text-[#0F172A]">Create document ETD shipment</p>
                    <p class="text-xs">Ship status: {{ str(($us09Status['document_ship']['transaction_status'] ?? 'not_tested'))->replace('_', ' ')->title() }}</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.document', $account) }}" class="mt-2 space-y-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                        @csrf
                        <label class="flex items-start gap-2 text-xs">
                            <input type="checkbox" name="confirm_us09_ship" value="1" required class="mt-0.5">
                            <span>I understand this creates a sandbox international ETD shipment.</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white" @disabled(! ($us09Status['document_ship_ready'] ?? false))>Create IntegratorUS09 Document Shipment</button>
                    </form>
                </li>
            </ol>
            @include('user_view.fedex_validation.partials.printed_scan_workflow', [
                'account' => $account,
                'testCaseKey' => 'IntegratorUS09_DOCUMENT',
                'shipStatus' => $us09Status['document_ship'] ?? [],
            ])
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-[#0F172A]">IntegratorUS10 — Consolidation / IPD</h3>
                    <p class="mt-1 text-sm text-[#64748B]">
                        @if ($us10Status['excluded'] ?? ! ($us10ValidationEnabled ?? false))
                            Excluded from active validation
                        @else
                            Runs Create → 6 Add Shipments → Confirm → Confirm Results against the dedicated Consolidation account. Child labels and the Consolidated Commercial Invoice are preserved when returned.
                        @endif
                    </p>
                </div>
                @if ($us10Status['excluded'] ?? ! ($us10ValidationEnabled ?? false))
                    <span class="rounded-full bg-[#F1F5F9] px-2 py-0.5 text-xs font-bold text-[#64748B]">Excluded</span>
                @endif
            </div>

            @if ($us10Status['excluded'] ?? ! ($us10ValidationEnabled ?? false))
                <div class="mt-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-xs text-[#475569]">
                    <p class="font-bold text-[#0F172A]">Consolidation / IPD not in scope</p>
                    <p class="mt-1">{{ $us10ExclusionNote ?? ($us10Status['exclusion_note'] ?? 'IntegratorUS10 Consolidation / IPD is excluded because Consolidation API is not a supported capability of this application and was not included in the current FedEx Developer Portal project.') }}</p>
                    <p class="mt-2">Historical Consolidation events remain stored for audit and do not block final readiness.</p>
                </div>
            @else
                <div class="mt-3 rounded-lg border border-[#DBEAFE] bg-[#EFF6FF] p-3 text-xs text-[#1E3A8A]">
                    <p class="font-bold">Next step</p>
                    <p class="mt-1">{{ $us10Status['next_action'] ?? 'Confirm configuration, then run the Consolidation chain once.' }}</p>
                </div>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                        <dt class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Consolidation account</dt>
                        <dd class="mt-1 font-semibold text-[#0F172A]">
                            @if ($us10Status['account_configured'] ?? false)
                                {{ $us10Status['account_last4'] ?? 'Configured' }}
                            @else
                                Missing
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                        <dt class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Shipper TIN</dt>
                        <dd class="mt-1 font-semibold text-[#0F172A]">{{ ($us10Status['tin_configured'] ?? false) ? 'Configured' : 'Missing' }}</dd>
                    </div>
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                        <dt class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Completed steps</dt>
                        <dd class="mt-1 font-semibold text-[#0F172A]">{{ $us10Status['completed_count'] ?? 0 }} / {{ $us10Status['required_count'] ?? 0 }}</dd>
                    </div>
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                        <dt class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Documents</dt>
                        <dd class="mt-1 font-semibold text-[#0F172A]">Labels {{ str($us10Status['child_labels_check'] ?? 'not_tested')->replace('_', ' ')->title() }} · CCI {{ str($us10Status['cci_check'] ?? 'not_tested')->replace('_', ' ')->title() }}</dd>
                    </div>
                </dl>

                @if (! empty($us10Status['steps']))
                    <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <p class="text-sm font-semibold text-[#0F172A]">Chain checklist</p>
                        <ul class="mt-2 grid gap-1 text-xs text-[#475569] sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($us10Status['steps'] as $step)
                                <li class="flex items-center justify-between gap-2 rounded-lg bg-white px-2 py-1.5">
                                    <span>{{ $step['label'] ?? $step['test_case_key'] }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusBadge($step['status'] ?? 'not_tested') }}">{{ str($step['status'] ?? 'not_tested')->replace('_', ' ')->title() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! empty($us10Status['last_failure']) && ($us10Status['completed_count'] ?? 0) < ($us10Status['required_count'] ?? 9))
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-950">
                        <p class="font-bold">Last Consolidation failure</p>
                        <p class="mt-1">
                            Stopped at {{ $us10Status['last_failure']['failed_step'] ?? 'unknown' }}
                            @if (! empty($us10Status['last_failure']['http_status']))
                                · HTTP {{ $us10Status['last_failure']['http_status'] }}
                            @endif
                            @if (! empty($us10Status['last_failure']['error_code']))
                                · {{ $us10Status['last_failure']['error_code'] }}
                            @endif
                        </p>
                        @if (! empty($us10Status['last_failure']['error_message']))
                            <p class="mt-1">{{ $us10Status['last_failure']['error_message'] }}</p>
                        @endif
                        @if ($us10Status['do_not_retry'] ?? false)
                            <p class="mt-2 font-semibold">Do not retry blindly. Fix Consolidation / IPD account entitlement with FedEx first, then run once.</p>
                        @endif
                    </div>
                @endif

                @if ($us10Status['uses_workbook_third_party_as_root'] ?? false)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
                        <p class="font-bold">Wrong account role in .env</p>
                        <p class="mt-1">Workbook <code>Test Account Numbers</code> lists US Test Account <strong>700257037</strong> for Integrator credentials. Workbook value {{ $us10Status['workbook_third_party_last4'] ?? '****6789' }} is only for US10 soldTo / THIRD_PARTY billing fields (already applied automatically). Set <code>FEDEX_VALIDATION_US10_CONSOLIDATION_ACCOUNT=700257037</code>, reload, then run once.</p>
                    </div>
                @elseif (! ($us10Status['account_configured'] ?? false) || ! ($us10Status['tin_configured'] ?? false))
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
                        Set <code>FEDEX_VALIDATION_US10_CONSOLIDATION_ACCOUNT=700257037</code> and <code>FEDEX_VALIDATION_US10_SHIPPER_TIN=59165821389</code> in <code>.env</code>, then reload this workspace.
                    </div>
                @else
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
                        A successful sandbox Consolidation run creates open consolidations and documents. Confirm before running during the final evidence window. Root account uses your env value; soldTo / THIRD_PARTY payors keep the workbook {{ $us10Status['workbook_third_party_last4'] ?? '****6789' }} billing account.
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account) }}" class="mt-3 space-y-2" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                    @csrf
                    <label class="flex items-start gap-2 text-xs text-[#475569]">
                        <input type="checkbox" name="confirm_us10_consolidation" value="1" required class="mt-0.5 rounded border-[#CBD5E1]" @disabled(! ($us10Status['ready_to_run'] ?? false))>
                        <span>I understand this creates a sandbox Consolidation / IPD workflow.</span>
                    </label>
                    <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60" @disabled(! ($us10Status['ready_to_run'] ?? false))>Run IntegratorUS10 Consolidation Chain</button>
                </form>

                @if (! empty($us10Status['child_label_artifacts']) || ! empty($us10Status['cci_artifact']))
                    <div class="mt-4 space-y-2 border-t border-[#E2E8F0] pt-4">
                        <p class="text-sm font-semibold text-[#0F172A]">Preserved Consolidation documents</p>
                        @foreach ($us10Status['child_label_artifacts'] ?? [] as $labelArtifact)
                            <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.artifacts.download', [$account, $labelArtifact->id]) }}" class="mr-2 inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#0052CC] hover:bg-[#EFF6FF]">
                                Download child label {{ $labelArtifact->package_sequence ?? '' }}
                            </a>
                        @endforeach
                        @if (! empty($us10Status['cci_artifact']))
                            <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.artifacts.download', [$account, $us10Status['cci_artifact']->id]) }}" class="inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#0052CC] hover:bg-[#EFF6FF]">
                                Download Consolidated Commercial Invoice
                            </a>
                        @endif
                    </div>
                @endif
            @endif
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-[#0F172A]">Global Territories — Canada</h3>
                    <p class="mt-1 text-sm text-[#64748B]">Package 7B — five locked Canada ship cases using the workbook CA test account. These run separately from US Package 6 evidence.</p>
                </div>
                <span class="rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-bold text-[#1D4ED8]">
                    {{ $canadaRegionalPreflight['passed'] ?? 0 }} / {{ $canadaRegionalPreflight['total'] ?? 0 }} Canada checks
                </span>
            </div>

            <dl class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Ship cases</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $canadaRegionalSummary['required_cases'] ?? 5 }} required</dd>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Labels & scans</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $canadaRegionalSummary['labels_passed'] ?? 0 }} labels · {{ $canadaRegionalSummary['scans_passed'] ?? 0 }} scan sets</dd>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Regional accounts</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $canadaRegionalAccounts['ready_accounts'] ?? 0 }} / {{ $canadaRegionalAccounts['total_accounts'] ?? 0 }} prepared</dd>
                </div>
            </dl>

            @if (! empty($canadaRegionalAccounts['accounts']))
                <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">Canada validation accounts</p>
                    <ul class="mt-2 space-y-1 text-sm text-[#475569]">
                        @foreach ($canadaRegionalAccounts['accounts'] as $regionalAccount)
                            <li>{{ $regionalAccount['label'] }} — {{ $regionalAccount['masked_account'] }} · {{ str($regionalAccount['status'])->replace('_', ' ')->title() }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-[#64748B]">Register each Canada workbook account through the existing validation registration flow before expecting live label success. Ship payloads already use the locked workbook account numbers.</p>
                </div>
            @endif

            <div class="mt-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($globalShipScenarios as $testCaseKey => $meta)
                    @php($scenarioKey = $meta['scenario_key'])
                    @php($shipStatus = $canadaRegionalSummary['case_statuses'][$testCaseKey] ?? [])
                    <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">{{ $testCaseKey }}</p>
                                <p class="mt-1 font-semibold text-[#0F172A]">{{ $shipStatus['label'] ?? ($meta['label_format'].' label') }}</p>
                                <p class="mt-1 text-sm text-[#64748B]">{{ $meta['expected_packages'] }} package(s) · Canada account context</p>
                            </div>
                            @if (! empty($shipStatus['transaction_representative']))
                                <span class="rounded-full bg-[#ECFDF5] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-[#047857]">Transaction rep</span>
                            @endif
                        </div>

                        <dl class="mt-3 space-y-1 text-xs text-[#475569]">
                            <div class="flex justify-between gap-3"><dt>API transaction</dt><dd class="font-semibold">{{ str($shipStatus['transaction_status'] ?? 'not_tested')->replace('_', ' ')->title() }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Expected service</dt><dd class="font-semibold">{{ $shipStatus['expected_service_type'] ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Generated labels</dt><dd class="font-semibold">{{ $shipStatus['generated_labels'] ?? '0 of '.$meta['expected_packages'] }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Printed scans</dt><dd class="font-semibold">{{ $shipStatus['printed_scans'] ?? '0 of '.$meta['expected_packages'] }}</dd></div>
                        </dl>

                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.global-ship', [$account, 'region' => 'CA', 'caseKey' => $testCaseKey]) }}" class="mt-3" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                            @csrf
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white">Run {{ $testCaseKey }}</button>
                        </form>

                        <p class="mt-2 text-xs text-[#64748B]">{{ $shipStatus['printing_instructions'] ?? 'Print the downloaded label before scanning.' }}</p>

                        <div class="mt-3 space-y-1">
                            @php($generatedArtifacts = collect($shipStatus['generated_label_artifacts'] ?? []))
                            @for ($i = 1; $i <= (int) $meta['expected_packages']; $i++)
                                @php($artifact = $generatedArtifacts->firstWhere('package_sequence', $i) ?? $generatedArtifacts->get($i - 1))
                                @if ($artifact)
                                    <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.artifacts.download', [$account, $artifact->id]) }}" class="inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#0052CC] hover:bg-[#EFF6FF]">
                                        Download generated label — package {{ $i }}
                                    </a>
                                @else
                                    <p class="text-xs text-[#94A3B8]">Package {{ $i }} label — run the case first</p>
                                @endif
                            @endfor
                        </div>

                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.scans.upload', $account) }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                            @csrf
                            <input type="hidden" name="test_case_key" value="{{ $testCaseKey }}">
                            <label class="block text-xs font-semibold text-[#475569]">Package sequence</label>
                            <select name="package_sequence" class="h-9 w-full rounded-lg border border-[#CBD5E1] px-2 text-sm">
                                @for ($i = 1; $i <= (int) $meta['expected_packages']; $i++)
                                    <option value="{{ $i }}">Package {{ $i }}</option>
                                @endfor
                            </select>
                            <label class="block text-xs font-semibold text-[#475569]">Scan DPI (minimum 600)</label>
                            <input type="number" name="scan_dpi" min="600" max="2400" value="600" required class="h-9 w-full rounded-lg border border-[#CBD5E1] px-2 text-sm">
                            <label class="block text-xs font-semibold text-[#475569]">Printed scan (PDF, PNG, or JPG)</label>
                            <p class="text-xs text-[#B45309]">Print the generated label first, then upload a 600 DPI scan of the printed paper — not the API file.</p>
                            <input type="file" name="scan" accept="application/pdf,image/png,image/jpeg" required class="block w-full text-xs">
                            <label class="flex items-start gap-2 text-xs text-[#475569]">
                                <input type="checkbox" name="printed_scan_attestation" value="1" required class="mt-0.5">
                                <span>I confirm this scan came from a physically printed Canada validation label at 600 DPI or higher.</span>
                            </label>
                            <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]">Upload printed scan</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        @if (in_array(\App\Services\Carriers\FedEx\Validation\FedExValidationScopeService::SCOPE_TRACKING, $requiredScopes, true))
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Tracking / Basic Integrated Visibility</h3>
                @if (! $trackingConfigured)
                    <p class="mt-2 text-sm text-amber-800">Not configured — set the FedEx tracking path before running this check.</p>
                @else
                    <p class="mt-1 text-sm text-[#64748B]">Select a tracking number from a successful sandbox ship run or enter one manually. includeDetailedScans=true is used automatically.</p>
                    <div class="mt-3 flex items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus('tracking')) }}">Tracking: {{ str($checkStatus('tracking'))->replace('_', ' ')->title() }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus('tracking_screenshot')) }}">Screenshot: {{ str($checkStatus('tracking_screenshot'))->replace('_', ' ')->title() }}</span>
                    </div>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.tracking', $account) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
                        @csrf
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Tracking number</span>
                            @if ($trackingNumbers !== [])
                                <select name="tracking_number" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3">
                                    @foreach ($trackingNumbers as $trackingNumber)
                                        <option value="{{ $trackingNumber }}">{{ $trackingNumber }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" name="tracking_number" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3" placeholder="Enter sandbox tracking number">
                            @endif
                        </label>
                        <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white md:self-end">Run tracking</button>
                    </form>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.tracking-screenshot.upload', $account) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Customer-facing tracking screenshot</span>
                            <input type="file" name="screenshot" accept="application/pdf,image/png" required class="block w-full text-sm">
                        </label>
                        <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-4 py-2 text-sm font-semibold text-[#475569]">Upload screenshot</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($shipCancelRequired)
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Shipment cancellation</h3>
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.cancel', $account) }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="block space-y-1 text-sm">
                        <span class="font-semibold text-[#475569]">Tracking number to cancel</span>
                        <input type="text" name="tracking_number" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 md:w-80">
                    </label>
                    <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Run cancellation</button>
                </form>
            </section>
        @endif

        @if ($tradeDocumentsRequired)
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Trade Documents</h3>
                @if (! $tradeDocumentsConfigured)
                    <p class="mt-2 text-sm text-amber-800">Not configured — set the FedEx Trade Documents upload path before running this check.</p>
                @else
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.trade-documents', $account) }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="block space-y-1 text-sm">
                            <span class="font-semibold text-[#475569]">Tracking number</span>
                            <input type="text" name="tracking_number" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 md:w-80">
                        </label>
                        <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Run Trade Documents upload</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Evidence cards</h3>
            <p class="mt-1 text-sm text-[#64748B]">Canonical event status, request/response availability, and artifact progress for each requirement.</p>
            <div class="mt-4 space-y-3">
                @foreach ($validationCards as $card)
                    <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-[#0F172A]">{{ $card['label'] }}</p>
                                <p class="mt-1 text-xs text-[#64748B]">{{ $card['baseline'] }}</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($card['status']) }}">{{ str($card['status'])->replace('_', ' ')->title() }}</span>
                        </div>
                        <dl class="mt-3 grid gap-1 text-xs text-[#475569] sm:grid-cols-2">
                            <div>Event #{{ $card['event_id'] ?? '—' }}</div>
                            <div>HTTP {{ $card['http_status'] ?? '—' }}</div>
                            <div>FedEx txn {{ $card['fedex_transaction_id'] ?? '—' }}</div>
                            <div>{{ $card['endpoint'] ?? '—' }} {{ $card['http_method'] ?? '' }}</div>
                            <div>Request JSON: {{ ($card['has_request_json'] ?? false) ? 'available' : 'missing' }}</div>
                            <div>Response JSON: {{ ($card['has_response_json'] ?? false) ? 'available' : 'missing' }}</div>
                        </dl>
                        @if (! empty($card['artifacts']))
                            <ul class="mt-2 space-y-1 text-xs text-[#64748B]">
                                @foreach ($card['artifacts'] as $artifact)
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span>Package {{ $artifact['package_sequence'] }} {{ $artifact['type'] }} — {{ str($artifact['status'])->replace('_', ' ') }}</span>
                                        @if ($artifact['type'] === 'label' && ! empty($artifact['artifact_id']))
                                            <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.artifacts.download', [$account, $artifact['artifact_id']]) }}" class="font-semibold text-[#0052CC] hover:underline">Download</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if (filled($card['explanation'] ?? null) && ($card['status'] ?? '') !== 'passed')
                            <p class="mt-2 text-xs text-amber-900">{{ $card['explanation'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Capability evidence status</h3>
            <dl class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($capabilityMatrix as $key => $cap)
                    @continue(in_array($key, ['connection_model', 'credentials_mode', 'readiness', 'blockers'], true))
                    @php($status = is_array($cap) ? ($cap['status'] ?? 'not_run') : 'not_run')
                    <div class="flex items-start justify-between gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm">
                        <span class="font-medium text-[#0F172A]">{{ str($key)->replace('_', ' ')->title() }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($status) }}">{{ $cap['label'] ?? str($status)->replace('_', ' ')->title() }}</span>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
@endsection
