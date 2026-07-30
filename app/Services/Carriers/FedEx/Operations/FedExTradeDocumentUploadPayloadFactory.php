<?php

namespace App\Services\Carriers\FedEx\Operations;

/**
 * Builds multipart Trade Documents Upload request metadata (no binary in logs).
 */
class FedExTradeDocumentUploadPayloadFactory
{
    public const MAX_IMAGE_BYTES = 512_000;

    public const MAX_DOCUMENT_BYTES = 5_242_880;

    public const MIN_DOCUMENT_BYTES = 1024;

    public const MAX_LETTERHEAD_WIDTH = 700;

    public const MAX_LETTERHEAD_HEIGHT = 50;

    public const MAX_SIGNATURE_WIDTH = 240;

    public const MAX_SIGNATURE_HEIGHT = 25;

    /**
     * @return array{
     *   endpoint_path: string,
     *   content_type: string,
     *   document_json: array<string, mixed>,
     *   attachment: array{filename: string, mime_type: string, absolute_path: string, size_bytes: int, width: int|null, height: int|null},
     *   redacted_multipart: array<string, mixed>
     * }
     */
    public function buildImageUpload(array $fixture): array
    {
        $upload = $fixture['upload'] ?? [];
        $path = $this->resolveExistingFile($upload);
        $filename = (string) ($upload['filename'] ?? basename($path));
        $imageType = strtoupper((string) ($upload['image_type'] ?? ''));
        $detectedMime = $this->detectMime($path);
        $this->assertAllowedImage($path, $filename, $detectedMime, $imageType);
        $dimensions = @getimagesize($path);
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : null;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : null;

        $documentJson = [
            'document' => [
                'referenceId' => (string) ($upload['reference_id'] ?? $fixture['key'] ?? 'US09'),
                'name' => $filename,
                'contentType' => $detectedMime,
                'meta' => [
                    'imageType' => $imageType,
                    'imageIndex' => (string) ($upload['image_index'] ?? ''),
                ],
            ],
            'rules' => [
                'workflowName' => (string) ($upload['workflow_name'] ?? 'LetterheadSignature'),
            ],
        ];

        return [
            'endpoint_path' => (string) config('carriers.fedex.trade_documents_upload_image_path', '/documents/v1/lhsimages/upload'),
            'content_type' => 'multipart/form-data',
            'document_json' => $documentJson,
            'attachment' => [
                'filename' => $filename,
                'mime_type' => $detectedMime,
                'absolute_path' => $path,
                'size_bytes' => (int) filesize($path),
                'width' => $width,
                'height' => $height,
            ],
            'redacted_multipart' => [
                'field_order' => ['document', 'attachment'],
                'document' => $documentJson,
                'attachment' => [
                    'filename' => $filename,
                    'contentType' => $detectedMime,
                    'bytes' => '[OMITTED_BINARY]',
                    'size_bytes' => (int) filesize($path),
                    'width' => $width,
                    'height' => $height,
                ],
            ],
        ];
    }

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
            'workflowName' => (string) ($upload['workflow_name'] ?? 'ETDPreshipment'),
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
        if (filled($upload['absolute_path'] ?? null)) {
            $absolute = (string) $upload['absolute_path'];
            abort_unless(is_file($absolute), 422, 'US09 sample file is missing: '.$absolute);
            abort_unless((int) filesize($absolute) > 0, 422, 'US09 sample file is empty: '.$absolute);

            return $absolute;
        }

        $relativePath = str_replace('\\', '/', ltrim((string) ($upload['relative_path'] ?? ''), '/'));
        abort_unless(str_starts_with($relativePath, 'resources/fedex-validation/us09/'), 422, 'US09 sample file path is outside the controlled validation directory.');

        $absolute = base_path($relativePath);
        abort_unless(is_file($absolute), 422, 'US09 sample file is missing: '.$relativePath.'. Provide a real workbook asset before the final evidence run.');
        abort_unless((int) filesize($absolute) > 0, 422, 'US09 sample file is empty: '.$relativePath);

        return $absolute;
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($path));

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function assertAllowedImage(string $path, string $filename, string $mime, string $imageType): void
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, ['png', 'gif'], true), 422, 'US09 image must be PNG or GIF.');
        abort_unless(in_array($mime, ['image/png', 'image/gif'], true), 422, 'US09 image MIME type is not allowed (detected: '.$mime.').');
        abort_unless((int) filesize($path) <= self::MAX_IMAGE_BYTES, 422, 'US09 image exceeds maximum size.');

        $dimensions = @getimagesize($path);
        abort_unless(is_array($dimensions), 422, 'US09 image is not a valid image file.');
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        abort_unless($width > 0 && $height > 0, 422, 'US09 image has invalid dimensions.');
        abort_unless(! ($width === 1 && $height === 1), 422, 'US09 image rejects placeholder 1×1 dimensions.');

        if ($imageType === 'LETTERHEAD') {
            abort_unless(
                $width <= self::MAX_LETTERHEAD_WIDTH && $height <= self::MAX_LETTERHEAD_HEIGHT,
                422,
                'US09 letterhead image exceeds max 700×50 pixels.'
            );
            abort_unless($width >= 2 && $height >= 2, 422, 'US09 letterhead image is too small to be a real letterhead.');
        } elseif ($imageType === 'SIGNATURE') {
            abort_unless(
                $width <= self::MAX_SIGNATURE_WIDTH && $height <= self::MAX_SIGNATURE_HEIGHT,
                422,
                'US09 signature image exceeds max 240×25 pixels.'
            );
            abort_unless($width >= 2 && $height >= 2, 422, 'US09 signature image is too small to be a real signature.');
        } else {
            abort(422, 'US09 image_type must be LETTERHEAD or SIGNATURE.');
        }
    }

    private function assertAllowedDocument(string $path, string $filename, string $mime): void
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        abort_unless($extension === 'pdf', 422, 'US09 document must be PDF.');
        abort_unless($mime === 'application/pdf', 422, 'US09 document MIME type must be application/pdf (detected: '.$mime.').');

        $size = (int) filesize($path);
        abort_unless($size <= self::MAX_DOCUMENT_BYTES, 422, 'US09 document exceeds maximum size.');
        abort_unless($size >= self::MIN_DOCUMENT_BYTES, 422, 'US09 document is too small to be a valid commercial invoice PDF.');

        $contents = (string) file_get_contents($path);
        abort_unless(str_starts_with($contents, '%PDF-'), 422, 'US09 document does not start with %PDF-.');
        abort_unless(str_contains($contents, '%%EOF'), 422, 'US09 document is missing a %%EOF marker.');
    }
}
