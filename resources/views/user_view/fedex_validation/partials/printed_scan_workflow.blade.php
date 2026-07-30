{{-- Requires: $account, $testCaseKey, $shipStatus --}}
@php
    $expectedPackages = (int) ($shipStatus['expected_package_count'] ?? 1);
    $generatedArtifacts = collect($shipStatus['generated_label_artifacts'] ?? []);
    $hasGeneratedLabels = $generatedArtifacts->isNotEmpty();
    $transactionPassed = ($shipStatus['transaction_status'] ?? '') === 'passed';
@endphp

<div class="mt-4 space-y-3 border-t border-[#E2E8F0] pt-4">
    <p class="text-sm font-semibold text-[#0F172A]">Generated label &amp; printed scan</p>
    <p class="text-xs text-[#64748B]">{{ $shipStatus['printing_instructions'] ?? 'Print the downloaded label before scanning.' }}</p>

    <dl class="space-y-1 text-xs text-[#475569]">
        <div class="flex justify-between gap-3"><dt>API transaction</dt><dd class="font-semibold">{{ str($shipStatus['transaction_status'] ?? 'not_tested')->replace('_', ' ')->title() }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Generated labels</dt><dd class="font-semibold">{{ $shipStatus['generated_labels'] ?? ('0 of '.$expectedPackages) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Printed scans</dt><dd class="font-semibold">{{ $shipStatus['printed_scans'] ?? ('0 of '.$expectedPackages) }}</dd></div>
    </dl>

    <div class="space-y-1">
        @for ($i = 1; $i <= $expectedPackages; $i++)
            @php($artifact = $generatedArtifacts->firstWhere('package_sequence', $i) ?? $generatedArtifacts->get($i - 1))
            @if ($artifact)
                <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation.artifacts.download', [$account, $artifact->id]) }}" class="inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#0052CC] hover:bg-[#EFF6FF]">
                    Download generated label — package {{ $i }}
                </a>
            @else
                <p class="text-xs text-[#94A3B8]">Package {{ $i }} label — complete the shipment step first</p>
            @endif
        @endfor
    </div>

    @if ($hasGeneratedLabels || $transactionPassed)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">
            <p class="font-bold">Printed scan workflow (required by FedEx)</p>
            <ol class="mt-2 list-decimal space-y-1 pl-4">
                <li>Download the generated label for this package.</li>
                <li>Print it on the correct stock (laser printer, actual size, no scaling).</li>
                <li>Scan the printed paper at 600 DPI or higher (PNG/JPG recommended).</li>
                <li>Upload that scan file here — not the downloaded API label.</li>
            </ol>
        </div>

        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.validation.scans.upload', $account) }}" enctype="multipart/form-data" class="space-y-2">
            @csrf
            <input type="hidden" name="test_case_key" value="{{ $testCaseKey }}">
            <label class="block text-xs font-semibold text-[#475569]">Package sequence</label>
            <select name="package_sequence" class="h-9 w-full rounded-lg border border-[#CBD5E1] px-2 text-sm">
                @for ($i = 1; $i <= $expectedPackages; $i++)
                    <option value="{{ $i }}">Package {{ $i }}</option>
                @endfor
            </select>
            <label class="block text-xs font-semibold text-[#475569]">Scan DPI (minimum 600)</label>
            <input type="number" name="scan_dpi" min="600" max="2400" value="600" required class="h-9 w-full rounded-lg border border-[#CBD5E1] px-2 text-sm">
            <label class="block text-xs font-semibold text-[#475569]">Printed scan (PDF, PNG, or JPG)</label>
            <p class="text-xs text-[#B45309]">Do not upload the downloaded API label file. Print first, then scan the physical print.</p>
            <input type="file" name="scan" accept="application/pdf,image/png,image/jpeg" required class="block w-full text-xs">
            <label class="flex items-start gap-2 text-xs text-[#475569]">
                <input type="checkbox" name="printed_scan_attestation" value="1" required class="mt-0.5">
                <span>I confirm that this file was created by physically printing the generated FedEx label and scanning the printed label at 600 DPI or higher without scaling.</span>
            </label>
            <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]">Upload printed scan</button>
        </form>
    @endif
</div>
