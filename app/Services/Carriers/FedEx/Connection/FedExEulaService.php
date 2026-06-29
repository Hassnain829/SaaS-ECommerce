<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FedExEulaService
{
    private const MIN_PDF_BYTES = 10_000;

    public function __construct(
        private readonly FedExConfig $config,
    ) {}

    public function documentPath(): string
    {
        return $this->config->eulaPath();
    }

    public function isAvailable(): bool
    {
        return File::isFile($this->documentPath());
    }

    public function isValid(): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $path = $this->documentPath();

        if (! is_readable($path)) {
            return false;
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return false;
        }

        $size = filesize($path);
        if ($size === false || $size < self::MIN_PDF_BYTES) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            return false;
        }

        if ($this->config->eulaVersion() === '' || $this->config->eulaExpectedPages() <= 0) {
            return false;
        }

        $expectedHash = $this->config->eulaExpectedSha256();

        return $expectedHash !== '' && hash_equals($expectedHash, $this->hash());
    }

    public function assertValid(): void
    {
        if (! $this->isValid()) {
            throw new RuntimeException('FedEx End User License Agreement document is missing or invalid. Contact a platform administrator.');
        }
    }

    public function version(): string
    {
        return $this->config->eulaVersion();
    }

    public function formNumber(): string
    {
        return $this->config->eulaFormNumber();
    }

    public function expectedPages(): int
    {
        return $this->config->eulaExpectedPages();
    }

    public function hash(): string
    {
        $path = $this->documentPath();

        if (! File::isFile($path)) {
            throw new RuntimeException('FedEx End User License Agreement file is missing.');
        }

        $hash = hash_file('sha256', $path);

        return is_string($hash) ? $hash : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        $path = $this->documentPath();

        return [
            'title' => 'FedEx End User License Agreement (3rd Party Hosted)',
            'form_number' => $this->formNumber(),
            'version' => $this->version(),
            'expected_pages' => $this->expectedPages(),
            'sha256' => $this->isAvailable() ? $this->hash() : null,
            'file_size' => File::isFile($path) ? filesize($path) : null,
            'mime_type' => $this->mimeType(),
            'hosted_inside_application' => true,
            'valid' => $this->isValid(),
        ];
    }

    public function mimeType(): string
    {
        return 'application/pdf';
    }
}
