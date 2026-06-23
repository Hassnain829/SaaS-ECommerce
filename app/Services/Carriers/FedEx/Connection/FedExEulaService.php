<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FedExEulaService
{
    public function __construct(
        private readonly FedExConfig $config,
    ) {}

    public function isAvailable(): bool
    {
        return File::isFile($this->config->eulaPath());
    }

    public function version(): string
    {
        return $this->config->eulaVersion();
    }

    public function html(): string
    {
        $path = $this->config->eulaPath();

        if (! File::isFile($path)) {
            throw new RuntimeException('FedEx End User License Agreement file is missing. Ask a platform administrator to add resources/legal/fedex/end_user_license_agreement.html.');
        }

        return File::get($path);
    }

    public function hash(): string
    {
        return hash('sha256', $this->html());
    }
}
