<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\Shipment;
use App\Models\Store;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Persists FedEx label / document artifacts outside the database.
 */
final class FedExLabelArtifactStore
{
    /**
     * @param  array<string, mixed>  $label
     * @return array{disk: string, path: string, url: ?string, image_type: string, tracking_number: ?string, bytes: int}|null
     */
    public function storeLabel(Store $store, Shipment $shipment, array $label, int $sequence): ?array
    {
        $encoded = $label['encoded_label'] ?? null;
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $imageType = strtoupper((string) ($label['image_type'] ?? 'PDF'));
        $extension = match ($imageType) {
            'PNG' => 'png',
            'ZPLII', 'ZPL' => 'zpl',
            default => 'pdf',
        };

        $disk = (string) config('carriers.fedex.label_storage_disk', 'local');
        $path = sprintf(
            'fedex/labels/%d/%d/%s-%d.%s',
            (int) $store->id,
            (int) $shipment->id,
            Str::lower(Str::substr(hash('sha256', (string) ($label['tracking_number'] ?? $shipment->id)), 0, 16)),
            $sequence,
            $extension,
        );

        $filesystem = Storage::disk($disk);
        $written = $filesystem->put($path, $binary);
        if ($written !== true) {
            return null;
        }

        if (! $filesystem->exists($path)) {
            return null;
        }

        $size = $filesystem->size($path);
        if (! is_int($size) || $size <= 0 || $size !== strlen($binary)) {
            try {
                $filesystem->delete($path);
            } catch (\Throwable) {
                // ignore cleanup failure
            }

            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'url' => $this->safeUrl($disk, $path),
            'image_type' => $imageType === 'ZPLII' ? 'ZPL' : $imageType,
            'tracking_number' => is_string($label['tracking_number'] ?? null) ? $label['tracking_number'] : null,
            'bytes' => $size,
        ];
    }

    private function safeUrl(string $disk, string $path): ?string
    {
        try {
            return Storage::disk($disk)->url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
