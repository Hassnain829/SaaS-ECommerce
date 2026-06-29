@extends('layouts.user.user-sidebar')

@section('title', 'FedEx EULA | BaaS Core')

@php
    $viewerId = 'fedex-eula-viewer-'.$session->id;
    $fedexEulaViewerConfig = [
        'viewerId' => $viewerId,
        'documentUrl' => route('settings.shipping.fedex-integrator.eula.document', $session),
        'expectedPages' => (int) ($eulaExpectedPages ?? 11),
        'documentHash' => $eulaDocumentHash,
        'scrollCompleteUrl' => route('settings.shipping.fedex-integrator.eula.scroll-complete', $session),
        'csrfToken' => csrf_token(),
    ];
@endphp

@push('styles')
    <style>
        @media print {
            @page {
                size: portrait;
                margin: 10mm;
            }

            html,
            body,
            .merchant-shell,
            main,
            .flex-1 {
                overflow: visible !important;
                height: auto !important;
                min-height: 0 !important;
                display: block !important;
            }

            aside,
            #sidebarOverlay,
            header,
            .fedex-eula-no-print,
            [data-fedex-eula-loading],
            [data-fedex-eula-sentinel],
            [data-fedex-eula-error] {
                display: none !important;
            }

            .fedex-eula-print-area {
                position: static !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
            }

            .fedex-eula-scroll-viewport,
            .fedex-eula-scroll-viewport.fedex-eula-printing {
                max-height: none !important;
                height: auto !important;
                overflow: visible !important;
                border: 0 !important;
                background: #fff !important;
                padding: 0 !important;
            }

            .fedex-eula-page {
                break-inside: avoid;
                page-break-inside: avoid;
                page-break-after: always;
                margin: 0 0 8mm 0;
                border: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            .fedex-eula-page:last-child {
                page-break-after: auto;
            }

            .fedex-eula-page canvas {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                max-width: 100% !important;
            }

            .fedex-eula-print-evidence {
                display: block !important;
                margin-top: 8mm;
                padding-top: 4mm;
                border-top: 1px solid #CBD5E1;
                page-break-inside: avoid;
            }

            .fedex-eula-print-evidence .fedex-eula-print-actions {
                display: none !important;
            }

            .fedex-eula-print-submit-label {
                display: block !important;
                font-weight: 700;
                color: #0F172A;
            }
        }

        .fedex-eula-print-submit-label {
            display: none;
        }
    </style>
@endpush

@section('content')
    <div
        class="mx-auto max-w-[900px] space-y-6"
        x-data="{
            viewerId: @js($viewerId),
            accepted: false,
            scrollCompleted: @json($scrollCompleted ?? false),
            documentValid: @json($eulaValid ?? false),
            loading: true,
            renderError: null,
            pageCountMismatch: false,
            pagesRendered: 0,
            canSubmit() {
                return this.documentValid
                    && ! this.pageCountMismatch
                    && this.pagesRendered === {{ (int) ($eulaExpectedPages ?? 11) }}
                    && this.scrollCompleted
                    && this.accepted;
            },
        }"
        @fedex-eula-scroll-complete.window="if ($event.detail?.viewerId === viewerId) scrollCompleted = true"
        @fedex-eula-render-state.window="
            if ($event.detail?.viewerId !== viewerId) return;
            loading = !! $event.detail.loading;
            renderError = $event.detail.renderError || null;
            pageCountMismatch = !! $event.detail.pageCountMismatch;
            pagesRendered = $event.detail.pagesRendered || 0;
        "
    >
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm fedex-eula-print-area">
            <div class="fedex-eula-no-print">
                <h2 class="text-xl font-semibold text-[#0F172A]">
                    @if ($validationEulaReview ?? false)
                        FedEx validation — Hosted End User License Agreement
                    @else
                        Step 2 — FedEx End User License Agreement
                    @endif
                </h2>
            </div>

            <div class="mt-2 text-sm text-[#475569]">
                <p class="font-semibold text-[#0F172A]">FedEx End User License Agreement</p>
                <p>{{ $eulaVersion }}</p>
                <p>{{ (int) ($eulaExpectedPages ?? 11) }} pages</p>
            </div>

            @unless ($eulaAvailable && $eulaValid)
                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    FedEx EULA file is missing or invalid. A platform administrator must add the official PDF before merchants can continue.
                </p>
            @else
                <div
                    id="{{ $viewerId }}"
                    data-fedex-eula-config='@json($fedexEulaViewerConfig)'
                    class="fedex-eula-scroll-viewport mt-4 max-h-[520px] overflow-y-auto rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4"
                >
                    <p data-fedex-eula-loading class="text-sm text-[#64748B]" x-show="loading">Loading agreement pages…</p>
                    <p data-fedex-eula-error class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"></p>
                    <p x-show="renderError" x-text="renderError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"></p>
                    <div data-fedex-eula-pages class="space-y-4"></div>
                    <div data-fedex-eula-sentinel class="h-8"></div>
                </div>

                <p x-show="! scrollCompleted && pagesRendered === {{ (int) ($eulaExpectedPages ?? 11) }}" class="mt-2 text-xs text-[#64748B]">
                    Scroll to the end of the agreement to enable acceptance.
                </p>

                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.eula.accept', $session) }}" class="fedex-eula-print-evidence mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="document_hash" value="{{ $eulaDocumentHash }}">
                    <label class="flex items-start gap-2 text-sm text-[#475569]">
                        <input
                            type="checkbox"
                            name="read_and_accept_eula"
                            value="1"
                            required
                            class="mt-1 rounded border-[#CBD5E1]"
                            x-model="accepted"
                        >
                        <span>I have read and agree to the FedEx End User License Agreement and confirm that I am authorized to accept it on behalf of this business.</span>
                    </label>
                    <div class="fedex-eula-print-actions flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="! canSubmit()"
                        >
                            I accept
                        </button>
                        <button type="button" onclick="window.print()" class="rounded-lg border border-[#CBD5E1] px-4 py-2 text-sm font-semibold text-[#475569]">
                            Print / Save EULA evidence
                        </button>
                        <a href="{{ route('settings.shipping.fedex-integrator.eula.document', $session) }}" target="_blank" rel="noopener" class="rounded-lg border border-[#CBD5E1] px-4 py-2 text-sm font-semibold text-[#475569]">
                            Download a copy
                        </a>
                    </div>
                    <p class="fedex-eula-print-submit-label text-sm font-semibold text-[#0F172A]">I accept</p>
                </form>
            @endunless

            <form method="POST" action="{{ route('settings.shipping.fedex-integrator.cancel', $session) }}" class="mt-3 fedex-eula-no-print">
                @csrf
                <button class="text-sm font-semibold text-[#64748B]">Cancel</button>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/fedex-eula-viewer.js'])
@endpush
