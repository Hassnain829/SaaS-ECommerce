<?php

namespace App\Services\Carriers\FedEx\Operations;

/**
 * Builds multipart Trade Documents Upload request metadata (document/PDF only).
 */
class FedExTradeDocumentUploadPayloadFactory
{
    public const MAX_DOCUMENT_BYTES = 5_242_880;

    public const MIN_DOCUMENT_BYTES = 1024;

    /**
     * @return array{
     *   endpoint_path: string,
     *   content_type: string,
     *   document_json: array<string, mixed>,
     *   attachment: array{filename: string, mime_type: string, absolute_path: string, size_bytes: int, width: int|null, height: int|null},
     *   redacted_multipart: array<string, mixed>
     * }
     */
    public function buildDocumentUpload(array $fixture): array
    {
        $upload = $fixture['upload'] ?? [];
        $path = $this->resolveExistingFile($upload);
        $filename = (string) ($upload['filename'] ?? basename($path));
        $detectedMime = $this->detectMime($path);
        $this->assertAllowedDocument($path, $filename, $detectedMime);

        $documentJson = [
            'workflowName' => (string) ($upload['workflow_name'] ?? 'ETDPreShipment'),
            'name' => $filename,
            'contentType' => $detectedMime,
            'meta' => [
                'shipDocumentType' => (string) ($upload['ship_document_type'] ?? 'COMMERCIAL_INVOICE'),
                'originCountryCode' => (string) ($upload['origin_country_code'] ?? 'US'),
                'destinationCountryCode' => (string) ($upload['destination_country_code'] ?? ''),
            ],
        ];

        if (filled($upload['carrier_code'] ?? null)) {
            $documentJson['carrierCode'] = (string) $upload['carrier_code'];
        }

        return [
            'endpoint_path' => (string) config('carriers.fedex.trade_documents_upload_document_path', '/documents/v1/etds/upload'),
            'content_type' => 'multipart/form-data',
            'document_json' => $documentJson,
            'attachment' => [
                'filename' => $filename,
                'mime_type' => $detectedMime,
                'absolute_path' => $path,
                'size_bytes' => (int) filesize($path),
                'width' => null,
                'height' => null,
            ],
            'redacted_multipart' => [
                'field_order' => ['document', 'attachment'],
                'document' => $documentJson,
                'attachment' => [
                    'filename' => $filename,
                    'contentType' => $detectedMime,
                    'bytes' => '[OMITTED_BINARY]',
                    'size_bytes' => (int) filesize($path),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $upload
     */
    private function resolveExistingFile(array $upload): string
    {
        abort_unless(
            ! filled($upload['relative_path'] ?? null),
            422,
            'Trade document upload rejects non-authoritative relative file paths.'
        );
        abort_unless(filled($upload['absolute_path'] ?? null), 422, 'Trade document upload requires an absolute file path.');

        $absolute = (string) $upload['absolute_path'];
        abort_unless(is_file($absolute), 422, 'Trade document file is missing: '.$absolute);
        abort_unless((int) filesize($absolute) > 0, 422, 'Trade document file is empty: '.$absolute);

        return $absolute;
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($path));

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function assertAllowedDocument(string $path, string $filename, string $mime): void
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        abort_unless($extension === 'pdf', 422, 'Trade document must be PDF.');
        abort_unless($mime === 'application/pdf', 422, 'Trade document MIME type must be application/pdf (detected: '.$mime.').');

        $size = (int) filesize($path);
        abort_unless($size <= self::MAX_DOCUMENT_BYTES, 422, 'Trade document exceeds maximum size.');
        abort_unless($size >= self::MIN_DOCUMENT_BYTES, 422, 'Trade document is too small to be a valid commercial invoice PDF.');

        $contents = (string) file_get_contents($path);
        abort_unless(str_starts_with($contents, '%PDF-'), 422, 'Trade document does not start with %PDF-.');
        abort_unless(str_contains($contents, '%%EOF'), 422, 'Trade document is missing a %%EOF marker.');
    }
}
