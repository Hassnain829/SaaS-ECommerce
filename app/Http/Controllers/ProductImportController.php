<?php

namespace App\Http\Controllers;

use App\Catalog\ProductImportField;
use App\Jobs\ProcessProductImportJob;
use App\Jobs\RetryFailedProductImportRowsJob;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\Store;
use App\Services\Catalog\ProductImportAutoColumnMapper;
use App\Services\Catalog\ProductImportMappingValidator;
use App\Services\Catalog\ProductImportMediaProgress;
use App\Services\Catalog\ProductImportPreviewService;
use App\Services\Catalog\ProductImportProcessor;
use App\Services\Catalog\ProductImportSpreadsheetReader;
use App\Services\Catalog\ProductImportStaleHandler;
use App\Support\Catalog\ProductImportHeaderNormalizer;
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
            if (ProductImportHeaderNormalizer::hasCaseInsensitiveDuplicateHeaders($headers)) {
                throw new \RuntimeException(
                    'Duplicate column headers (ignoring capitalization) would cause values to overwrite each other. Give each column a unique name, then try again.'
                );
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

        $guessedMapping = ProductImportAutoColumnMapper::guess($headers);
        $guessedCustom = ProductImportAutoColumnMapper::suggestCustomMappings($headers, $guessedMapping);
        $validationErrors = ProductImportMappingValidator::validate($guessedMapping, $headers, $guessedCustom);

        if ($validationErrors === []) {
            $previewError = $this->persistMappingAndBuildPreview($import, $guessedMapping, $guessedCustom);
            if ($previewError !== null) {
                return redirect()
                    ->route('products.import.mapping', ['productImportId' => $import->id])
                    ->withErrors(['preview' => $previewError])
                    ->with('import_notice', 'We matched your columns automatically, but the preview step reported an issue. Adjust mapping if needed, then build preview again.');
            }

            Log::channel('import')->info('product_import_auto_mapped_to_preview', [
                'import_id' => $import->id,
                'store_id' => $store->id,
            ]);

            return redirect()->route('products.import.preview', ['productImportId' => $import->id]);
        }

        $import->update([
            'column_mapping' => $guessedMapping,
            'custom_field_mappings' => ProductImportProcessor::normalizeCustomMappings($guessedCustom),
        ]);

        return redirect()
            ->route('products.import.mapping', ['productImportId' => $import->id])
            ->with(
                'import_notice',
                'We pre-filled column matches from your header row. Review the mapping (especially required fields marked with *), then click Build preview.'
            );
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

        $fieldRules = [];
        foreach (array_keys(ProductImportField::labels()) as $field) {
            $fieldRules['column_mapping.'.$field] = ['nullable', 'string', 'max:200'];
        }

        $validated = $request->validate(array_merge([
            'column_mapping' => ['required', 'array'],
        ], $fieldRules));
        $mapping = $validated['column_mapping'] ?? [];

        $rawCustom = $request->input('custom_field_mappings');
        if ($rawCustom === null) {
            $rawCustom = [];
        }
        if (! is_array($rawCustom)) {
            return back()->withErrors(['custom_field_mappings' => 'Custom field mappings must be a valid list.'])->withInput();
        }

        $validationErrors = ProductImportMappingValidator::validate($mapping, $headers, $rawCustom);
        if ($validationErrors !== []) {
            return back()->withErrors(['column_mapping' => implode(' ', $validationErrors)])->withInput();
        }

        $normalizedCustom = ProductImportProcessor::normalizeCustomMappings($rawCustom);

        $previewError = $this->persistMappingAndBuildPreview($productImport, $mapping, $normalizedCustom);
        if ($previewError !== null) {
            return back()->withErrors(['preview' => $previewError])->withInput();
        }

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

    public function reopenMapping(Request $request, int $productImportId): RedirectResponse
    {
        $productImport = $this->resolveImport($request, $productImportId);
        $this->authorizeImport($request, $productImport);
        $productImport = ProductImportStaleHandler::resolveIfStale($productImport->fresh());
        $productImport->refresh();

        if (($productImport->headers ?? []) === []) {
            return redirect()
                ->route('products.import.history')
                ->withErrors(['import' => 'This import no longer has column headers stored. Upload the file again to start a new import.']);
        }

        if (! Storage::disk($productImport->stored_disk)->exists($productImport->stored_path)) {
            return redirect()
                ->route('products.import.history')
                ->withErrors(['import' => 'The original file is no longer on the server. Upload it again to start a new import.']);
        }

        $st = $productImport->normalizedStatus();

        if (in_array($st, [ProductImport::STATUS_QUEUED, ProductImport::STATUS_PROCESSING], true)) {
            return redirect()
                ->route('products.import.result', ['productImportId' => $productImport->id])
                ->withErrors(['import' => 'This import is still running. When the status shows Finished, you can re-edit column mapping from import history or this page.']);
        }

        // Already on the mapping / preview path (e.g. double submit or back navigation): send them to mapping without wiping.
        if (in_array($st, [ProductImport::STATUS_PARSED, ProductImport::STATUS_PREVIEWED], true)) {
            return redirect()
                ->route('products.import.mapping', ['productImportId' => $productImport->id])
                ->with('import_notice', 'Adjust your column mapping below, then save to build a new preview.');
        }

        if (! in_array($st, [ProductImport::STATUS_COMPLETED, ProductImport::STATUS_FAILED], true)) {
            return redirect()
                ->route('products.import.history')
                ->withErrors(['import' => 'Re-editing mapping from import history is only available once the import shows Finished or Could not finish. If you are still mapping a new upload, open Map columns from your import notifications or start from Import products.']);
        }

        $productImport->update([
            'status' => ProductImport::STATUS_PARSED,
            'preview_summary' => null,
            'completed_at' => null,
            'failure_message' => null,
            'queued_at' => null,
            'started_at' => null,
            'last_processed_row' => 0,
            'total_rows' => null,
            'import_state' => null,
            'result_summary' => null,
        ]);

        Log::channel('import')->info('product_import_mapping_reopened', [
            'import_id' => $productImport->id,
            'store_id' => $productImport->store_id,
        ]);

        return redirect()
            ->route('products.import.mapping', ['productImportId' => $productImport->id])
            ->with(
                'import_notice',
                'You are re-editing a previous import using the same file. Adjust column mapping (for example images), open preview, then confirm to run again. Rows with the same product SKU are updated in your catalog—they are not duplicated.'
            );
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
     * @param  array<string, mixed>  $mapping
     * @param  array<int, array<string, string>>|array<int, mixed>  $customMappings  Normalized custom rows or raw request rows (normalized before call).
     */
    private function persistMappingAndBuildPreview(ProductImport $productImport, array $mapping, array $customMappings): ?string
    {
        $normalizedCustom = ProductImportProcessor::normalizeCustomMappings($customMappings);

        $productImport->update([
            'column_mapping' => $mapping,
            'custom_field_mappings' => $normalizedCustom,
        ]);

        $preview = $this->previewService->build($productImport->fresh());
        if (isset($preview['error'])) {
            return (string) $preview['error'];
        }

        $productImport->update([
            'preview_summary' => $preview,
            'status' => ProductImport::STATUS_PREVIEWED,
        ]);

        return null;
    }
}
