<?php

namespace App\Http\Controllers;

use App\Catalog\ProductImportField;
use App\Jobs\ProcessProductImportJob;
use App\Jobs\RetryFailedProductImportRowsJob;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\Store;
use App\Services\Catalog\ProductImportPreviewService;
use App\Services\Catalog\ProductImportMediaProgress;
use App\Services\Catalog\ProductImportProcessor;
use App\Services\Catalog\ProductImportSpreadsheetReader;
use App\Services\Catalog\ProductImportStaleHandler;
use App\Support\Catalog\ProductImportQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductImportSpreadsheetReader $spreadsheetReader,
        private readonly ProductImportPreviewService $previewService,
    ) {}

    public function create(Request $request): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return view('user_view.product_import.upload', [
            'selectedStore' => $store,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:15360'],
        ]);

        $file = $validated['file'];
        $original = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'csv');

        $relativeDir = 'product-imports/'.$store->id;
        $storedName = uniqid('imp_', true).'.'.$ext;
        $path = $file->storeAs($relativeDir, $storedName, 'local');

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $request->user()?->id,
            'original_filename' => $original,
            'stored_disk' => 'local',
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_extension' => $ext,
            'status' => ProductImport::STATUS_UPLOADED,
        ]);

        try {
            $abs = Storage::disk('local')->path($path);
            $headers = $this->spreadsheetReader->readHeaderRow($abs, $ext);
            if ($headers === [] || (count(array_filter($headers, static fn ($h) => $h !== '')) === 0)) {
                throw new \RuntimeException('The file has no header row or no columns.');
            }
            $import->update([
                'headers' => $headers,
                'status' => ProductImport::STATUS_PARSED,
            ]);

            Log::channel('import')->info('product_import_upload_stored', [
                'import_id' => $import->id,
                'store_id' => $store->id,
                'disk' => 'local',
                'path' => $path,
                'filename' => $original,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            $import->delete();

            return back()->withErrors(['file' => 'Could not read the file: '.$e->getMessage()]);
        }

        return redirect()
            ->route('products.import.mapping', ['productImportId' => $import->id]);
    }

    public function mapping(Request $request, int $productImportId): View
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);

        if (! in_array($productImport->status, [ProductImport::STATUS_PARSED, ProductImport::STATUS_PREVIEWED], true)) {
            abort(404);
        }

        $store = $request->attributes->get('currentStore');

        return view('user_view.product_import.mapping', [
            'selectedStore' => $store,
            'import' => $productImport,
            'fieldLabels' => ProductImportField::labels(),
            'headers' => $productImport->headers ?? [],
            'existingMapping' => $productImport->column_mapping ?? [],
            'existingCustomMappings' => $productImport->custom_field_mappings ?? [],
        ]);
    }

    public function saveMapping(Request $request, int $productImportId): RedirectResponse
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);

        if (! in_array($productImport->status, [ProductImport::STATUS_PARSED, ProductImport::STATUS_PREVIEWED], true)) {
            return redirect()
                ->route('products.import.mapping', ['productImportId' => $productImport->id])
                ->withErrors(['mapping' => 'Mapping can only be saved before the import is queued.']);
        }

        $headers = $productImport->headers ?? [];
        $headerSet = array_flip(array_filter($headers, static fn ($h) => is_string($h) && $h !== ''));

        $fieldRules = [];
        foreach (array_keys(ProductImportField::labels()) as $field) {
            $fieldRules['column_mapping.'.$field] = ['nullable', 'string', 'max:200'];
        }

        $validated = $request->validate(array_merge([
            'column_mapping' => ['required', 'array'],
        ], $fieldRules));
        $mapping = $validated['column_mapping'] ?? [];

        if (ProductImportField::hasPartialOptionSlotMapping($mapping)) {
            return back()->withErrors([
                'column_mapping' => 'For each option slot, map both the group label column and the value column, or leave that slot unused.',
            ])->withInput();
        }

        $structuredVariant = ProductImportField::usesStructuredVariantRows($mapping);
        $hasParentSkuColumn = is_string($mapping[ProductImportField::PARENT_SKU] ?? null) && $mapping[ProductImportField::PARENT_SKU] !== '';
        $hasSkuColumn = is_string($mapping[ProductImportField::SKU] ?? null) && $mapping[ProductImportField::SKU] !== '';
        if ($structuredVariant && ! $hasParentSkuColumn && ! $hasSkuColumn) {
            return back()->withErrors([
                'column_mapping' => 'For multi-row variants, map Parent product SKU or Product SKU so rows can be grouped into the correct product.',
            ])->withInput();
        }

        foreach (ProductImportField::requiredForImport() as $required) {
            if ($structuredVariant && $required === ProductImportField::SKU && $hasParentSkuColumn) {
                continue;
            }
            if (empty($mapping[$required]) || ! is_string($mapping[$required])) {
                return back()->withErrors([$required => 'This field must be mapped.'])->withInput();
            }
            if (! isset($headerSet[$mapping[$required]])) {
                return back()->withErrors([$required => 'Mapped column is not present in the file.'])->withInput();
            }
        }

        $sourcesUsed = [];
        foreach ($mapping as $field => $source) {
            if (! is_string($source) || $source === '') {
                continue;
            }
            if (isset($sourcesUsed[$source])) {
                return back()->withErrors(['column_mapping' => 'Each file column can only be mapped once ('.$source.').'])->withInput();
            }
            $sourcesUsed[$source] = $field;
        }

        $rawCustom = $request->input('custom_field_mappings');
        if ($rawCustom === null) {
            $rawCustom = [];
        }
        if (! is_array($rawCustom)) {
            return back()->withErrors(['custom_field_mappings' => 'Custom field mappings must be a valid list.'])->withInput();
        }

        $customErrors = $this->validateCustomFieldMappingsInput($rawCustom, $headerSet, $sourcesUsed);
        if ($customErrors !== []) {
            return back()->withErrors(['custom_field_mappings' => implode(' ', $customErrors)])->withInput();
        }

        $normalizedCustom = ProductImportProcessor::normalizeCustomMappings($rawCustom);

        $productImport->update([
            'column_mapping' => $mapping,
            'custom_field_mappings' => $normalizedCustom,
        ]);

        $preview = $this->previewService->build($productImport->fresh());
        if (isset($preview['error'])) {
            return back()->withErrors(['preview' => $preview['error']])->withInput();
        }

        $productImport->update([
            'preview_summary' => $preview,
            'status' => ProductImport::STATUS_PREVIEWED,
        ]);

        return redirect()->route('products.import.preview', ['productImportId' => $productImport->id]);
    }

    public function preview(Request $request, int $productImportId): View
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);

        if ($productImport->status !== ProductImport::STATUS_PREVIEWED) {
            abort(404);
        }

        $store = $request->attributes->get('currentStore');

        return view('user_view.product_import.preview', [
            'selectedStore' => $store,
            'import' => $productImport,
            'preview' => $productImport->preview_summary ?? [],
        ]);
    }

    public function confirm(Request $request, int $productImportId): RedirectResponse
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);

        if ($productImport->status !== ProductImport::STATUS_PREVIEWED) {
            return redirect()
                ->route('products.import.result', ['productImportId' => $productImport->id])
                ->withErrors(['import' => 'This import has already been submitted or is not ready to confirm.']);
        }

        if (! Storage::disk($productImport->stored_disk)->exists($productImport->stored_path)) {
            Log::channel('import')->error('product_import_file_missing_at_confirm', [
                'import_id' => $productImport->id,
                'store_id' => $productImport->store_id,
                'disk' => $productImport->stored_disk,
                'path' => $productImport->stored_path,
            ]);
            $productImport->update([
                'status' => ProductImport::STATUS_FAILED,
                'failure_message' => 'We could not find your uploaded file on the server. Please upload your spreadsheet again to start a new import.',
                'completed_at' => now(),
            ]);

            return redirect()
                ->route('products.import.result', ['productImportId' => $productImport->id])
                ->withErrors(['import' => 'Your file was no longer available on the server. Please upload it again.']);
        }

        $productImport->update([
            'status' => ProductImport::STATUS_QUEUED,
            'queued_at' => now(),
            'last_processed_row' => 0,
            'total_rows' => null,
            'import_state' => null,
            'failure_message' => null,
        ]);

        Log::channel('import')->info('product_import_confirm_dispatched', array_merge([
            'import_id' => $productImport->id,
            'store_id' => $productImport->store_id,
            'filename' => $productImport->original_filename,
            'runs_inline' => ProductImportQueue::runsInline(),
        ], ProductImportQueue::diagnostics()));

        ProcessProductImportJob::dispatch($productImport->id);

        $redirect = redirect()->route('products.import.result', ['productImportId' => $productImport->id]);

        $threshold = (int) config('product_import.recommend_async_above_rows', 400);
        $previewRows = (int) ($productImport->preview_summary['total_rows_sampled'] ?? 0);
        if ($threshold > 0 && $previewRows >= $threshold && ProductImportQueue::runsInline()) {
            $redirect = $redirect->with(
                'import_notice',
                'Your file looks large. On this setup imports run in the same step as your browser request; very big catalogs may take a while. If anything feels stuck, your technical contact can enable background imports for smoother performance.'
            );
        }

        return $redirect;
    }

    public function resume(Request $request, int $productImportId): RedirectResponse
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);
        $productImport = ProductImportStaleHandler::resolveIfStale($productImport);
        $productImport->refresh();

        if ($productImport->status === ProductImport::STATUS_COMPLETED) {
            return redirect()
                ->route('products.import.result', ['productImportId' => $productImport->id])
                ->withErrors(['import' => 'This import already completed.']);
        }

        $total = (int) ($productImport->total_rows ?? 0);
        $last = (int) ($productImport->last_processed_row ?? 0);
        if ($total < 1 || $last >= $total) {
            return redirect()
                ->route('products.import.result', ['productImportId' => $productImport->id])
                ->withErrors(['import' => 'Nothing to resume for this import.']);
        }

        $path = Storage::disk($productImport->stored_disk)->path($productImport->stored_path);
        if (! is_file($path)) {
            return redirect()
                ->route('products.import.result', ['productImportId' => $productImport->id])
                ->withErrors(['import' => 'The import file is no longer available; upload a new file to try again.']);
        }

        if ($productImport->status !== ProductImport::STATUS_FAILED) {
            return redirect()
                ->route('products.import.result', ['productImportId' => $productImport->id])
                ->withErrors(['import' => 'Resume is only available after an import has failed partway through.']);
        }

        $productImport->update([
            'status' => ProductImport::STATUS_QUEUED,
            'queued_at' => now(),
            'failure_message' => null,
        ]);

        Log::channel('import')->info('product_import_resume_dispatched', array_merge([
            'import_id' => $productImport->id,
            'store_id' => $productImport->store_id,
        ], ProductImportQueue::diagnostics()));

        ProcessProductImportJob::dispatch($productImport->id);

        return redirect()
            ->route('products.import.result', ['productImportId' => $productImport->id])
            ->with('import_notice', 'We picked up where you left off and are continuing with the remaining rows.');
    }

    public function history(Request $request): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);
        if (! $request->user()?->hasStoreRole($store, [Store::ROLE_OWNER, Store::ROLE_MANAGER])) {
            abort(403, 'You are not authorized to view import history for this store.');
        }

        $q = trim((string) $request->query('q', ''));
        $statusFilter = $request->query('status');
        $allowedStatuses = [
            ProductImport::STATUS_COMPLETED,
            ProductImport::STATUS_FAILED,
            ProductImport::STATUS_PROCESSING,
            ProductImport::STATUS_QUEUED,
            ProductImport::STATUS_PREVIEWED,
            ProductImport::STATUS_PARSED,
            ProductImport::STATUS_UPLOADED,
        ];

        $importsQuery = ProductImport::query()
            ->where('store_id', $store->id)
            ->with('creator:id,name')
            ->orderByDesc('id');

        if ($q !== '') {
            $importsQuery->where('original_filename', 'like', '%'.$q.'%');
        }
        if (is_string($statusFilter) && in_array($statusFilter, $allowedStatuses, true)) {
            $importsQuery->where('status', $statusFilter);
        }

        $imports = $importsQuery->paginate(20)->withQueryString();

        $imports->getCollection()->transform(function (ProductImport $import): ProductImport {
            return ProductImportStaleHandler::resolveIfStale($import);
        });

        return view('user_view.product_import.history', [
            'selectedStore' => $store,
            'imports' => $imports,
            'searchQ' => $q,
            'statusFilter' => is_string($statusFilter) && in_array($statusFilter, $allowedStatuses, true) ? $statusFilter : '',
        ]);
    }

    public function report(Request $request, int $productImportId): View
    {
        $import = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $import);
        $import = ProductImportStaleHandler::resolveIfStale($import);
        $import->loadMissing('creator:id,name');
        $store = $request->attributes->get('currentStore');

        $failedCount = (int) ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->count();

        $failedRows = ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->orderBy('row_number')
            ->paginate(25, ['*'], 'failed_page')
            ->withQueryString();

        $reasonGroups = ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->get()
            ->groupBy(fn (ProductImportRow $r): string => (string) ($r->error_message ?? ''))
            ->map(fn ($group, $key): object => (object) [
                'error_message' => (string) $key,
                'row_count' => $group->count(),
            ])
            ->sortByDesc('row_count')
            ->take(12)
            ->values();

        $canRetryFailed = in_array($import->status, [ProductImport::STATUS_COMPLETED, ProductImport::STATUS_FAILED], true)
            && $failedCount > 0;

        return view('user_view.product_import.report', [
            'selectedStore' => $store,
            'import' => $import,
            'failedRows' => $failedRows,
            'failedCount' => $failedCount,
            'reasonGroups' => $reasonGroups,
            'canRetryFailed' => $canRetryFailed,
        ]);
    }

    public function retryFailed(Request $request, int $productImportId): RedirectResponse
    {
        $import = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $import);

        if (! in_array($import->status, [ProductImport::STATUS_COMPLETED, ProductImport::STATUS_FAILED], true)) {
            return redirect()
                ->route('products.import.report', ['productImportId' => $import->id])
                ->withErrors(['retry' => 'You can retry rows only after an import has finished.']);
        }

        $failedCount = (int) ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->count();

        if ($failedCount < 1) {
            return redirect()
                ->route('products.import.report', ['productImportId' => $import->id])
                ->withErrors(['retry' => 'There are no failed rows to retry for this import.']);
        }

        RetryFailedProductImportRowsJob::dispatch($import->id);

        return redirect()
            ->route('products.import.report', ['productImportId' => $import->id])
            ->with('import_notice', 'We are working through the rows that did not go through. This page will reflect new results shortly—feel free to refresh in a moment.');
    }

    public function result(Request $request, int $productImportId): View
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);

        $productImport = ProductImportStaleHandler::resolveIfStale($productImport);

        $store = $request->attributes->get('currentStore');

        $path = Storage::disk($productImport->stored_disk)->path($productImport->stored_path);
        $canResume = $productImport->status === ProductImport::STATUS_FAILED
            && (int) ($productImport->total_rows ?? 0) > 0
            && (int) ($productImport->last_processed_row ?? 0) < (int) $productImport->total_rows
            && is_file($path);

        $importUsesBackgroundQueue = ! ProductImportQueue::runsInline();

        return view('user_view.product_import.result', [
            'selectedStore' => $store,
            'import' => $productImport,
            'canResume' => $canResume,
            'importUsesBackgroundQueue' => $importUsesBackgroundQueue,
        ]);
    }

    public function importProgress(Request $request, int $productImportId): JsonResponse
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);
        $productImport = ProductImportStaleHandler::resolveIfStale($productImport);

        return response()->json(ProductImportMediaProgress::snapshot($productImport));
    }

    public function template(): StreamedResponse
    {
        $headers = [
            'product_name',
            'sku',
            'base_price',
            'stock',
            'brand',
            'category',
            'tags',
            'description',
            'product_type',
            'image_urls',
            'supplier_code',
        ];

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, [
                'Sample T-Shirt',
                'DEMO-SKU-001',
                '29.99',
                '25',
                'Demo Brand',
                'Apparel|T-Shirts',
                'Featured|New Arrival',
                'A sample row for structure reference.',
                'physical',
                '',
                'SUP-7788',
            ]);
            fclose($out);
        }, 'product-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeImport(Request $request, ProductImport $productImport): void
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $productImport->store_id === (int) $store->id, 404);

        if (! $request->user()?->hasStoreRole($store, [Store::ROLE_OWNER, Store::ROLE_MANAGER])) {
            abort(403, 'You are not authorized to run catalog imports in this store.');
        }
    }

    private function resolveImport(Request $request, int $productImportId): ProductImport
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return ProductImport::query()
            ->where('store_id', $store->id)
            ->whereKey($productImportId)
            ->firstOrFail();
    }

    /**
     * @param  array<int, mixed>  $rawCustom
     * @param  array<string, int>  $headerSet
     * @param  array<string, string>  $sourcesUsed  source header => system field key
     * @return list<string>
     */
    private function validateCustomFieldMappingsInput(array $rawCustom, array $headerSet, array $sourcesUsed): array
    {
        $errors = [];
        $reserved = array_flip(array_map('strtolower', array_keys(ProductImportField::labels())));
        $keyPattern = '/^[a-zA-Z0-9_.-]{1,128}$/';

        $rows = [];
        foreach ($rawCustom as $idx => $row) {
            if (! is_array($row)) {
                $errors[] = 'Custom field row '.($idx + 1).' is invalid.';

                continue;
            }
            $source = trim((string) ($row['source'] ?? ''));
            $key = trim((string) ($row['key'] ?? ''));
            $scopeRaw = strtolower(trim((string) ($row['scope'] ?? 'product')));
            $scope = $scopeRaw === 'variant' ? 'variant' : 'product';

            if ($source === '' && $key === '') {
                continue;
            }
            if ($source === '' || $key === '') {
                $errors[] = 'Each custom field needs both a source column and a destination key.';

                continue;
            }
            if (! isset($headerSet[$source])) {
                $errors[] = 'Custom field source column "'.$source.'" is not in this file.';

                continue;
            }
            if (isset($sourcesUsed[$source])) {
                $errors[] = 'Column "'.$source.'" is already mapped to a catalog field and cannot be reused as a custom field.';

                continue;
            }
            if (preg_match($keyPattern, $key) !== 1) {
                $errors[] = 'Custom field key "'.$key.'" must be 1–128 characters (letters, numbers, underscore, dot, hyphen).';

                continue;
            }
            if (isset($reserved[strtolower($key)])) {
                $errors[] = 'Custom field key "'.$key.'" is reserved for a built-in import field.';

                continue;
            }

            $rows[] = ['source' => $source, 'key' => $key, 'scope' => $scope];
        }

        $seenSources = [];
        $seenKeys = [];
        foreach ($rows as $row) {
            if (isset($seenSources[$row['source']])) {
                $errors[] = 'Duplicate custom mapping for column "'.$row['source'].'".';
            }
            $seenSources[$row['source']] = true;

            $lk = strtolower($row['key']);
            if (isset($seenKeys[$lk])) {
                $errors[] = 'Duplicate custom field key "'.$row['key'].'".';
            }
            $seenKeys[$lk] = true;
        }

        return $errors;
    }
}
