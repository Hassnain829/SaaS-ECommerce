@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Validation Workspace | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 md:px-8">
        <div>
            <h1 class="font-poppins text-lg font-semibold text-[#0F172A] md:text-xl">FedEx validation workspace</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Sandbox integrator evidence preparation for {{ $account->display_name }}</p>
        </div>
        <a href="{{ route('shippingAutomation', ['tab' => 'carriers']) }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back to carriers</a>
    </header>
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

    <div class="mx-auto max-w-[1100px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Validation readiness</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#0F172A]">
                        {{ $preflight['completed_count'] ?? 0 }} of {{ $preflight['total_count'] ?? 0 }} required checks complete
                    </h2>
                    <p class="mt-2 text-sm text-[#64748B]">
                        @if ($ready)
                            Final FedEx submission export is available when you are ready to send the package.
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
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Baseline API runs</h3>
            <p class="mt-1 text-sm text-[#64748B]">Run locked baseline checks from this workspace. Results are recorded as canonical validation evidence.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['key' => 'address_validation', 'label' => 'Address Validation', 'route' => 'settings.shipping.carrier-accounts.fedex.validation.run.address', 'baseline' => 'IntegratorUS02 recipient baseline'],
                    ['key' => 'service_availability', 'label' => 'Service Availability', 'route' => 'settings.shipping.carrier-accounts.fedex.validation.run.service-availability', 'baseline' => 'Default origin to IntegratorUS02 destination'],
                    ['key' => 'rate_quote', 'label' => 'Comprehensive Rate Quote', 'route' => 'settings.shipping.carrier-accounts.fedex.validation.run.rate', 'baseline' => 'IntegratorUS02 package baseline'],
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

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Locked Ship baseline scenarios</h3>
            <p class="mt-1 text-sm text-[#64748B]">Fixed workbook mapping — format cannot be changed. Upload printed scans after each successful label run.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                @foreach ($lockedShipScenarios as $testCaseKey => $meta)
                    @php($scenarioKey = $meta['scenario_key'])
                    <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">{{ $testCaseKey }}</p>
                                <p class="mt-1 font-semibold text-[#0F172A]">{{ $meta['label_format'] }} / {{ $meta['label_stock_type'] }}</p>
                                <p class="mt-1 text-sm text-[#64748B]">{{ $meta['expected_packages'] }} package(s)</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge($checkStatus($scenarioKey.'_event')) }}">{{ str($checkStatus($scenarioKey.'_event'))->replace('_', ' ')->title() }}</span>
                        </div>
                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [$account, 'testCaseKey' => $testCaseKey]) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-bold text-white">Run locked {{ $meta['label_format'] }} label</button>
                        </form>
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
                        <p class="mt-2 text-xs text-[#64748B]">Print the downloaded label, scan it at 600+ DPI, then upload the scan below — not the raw API file.</p>
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
                            <label class="block text-xs font-semibold text-[#475569]">Printed scan (PDF or PNG)</label>
                            <input type="file" name="scan" accept="application/pdf,image/png" required class="block w-full text-xs">
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
