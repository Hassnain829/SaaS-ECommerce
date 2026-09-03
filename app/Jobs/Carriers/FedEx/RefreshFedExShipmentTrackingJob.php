<?php

namespace App\Jobs\Carriers\FedEx;

use App\Models\Shipment;
use App\Services\Carriers\FedEx\Operations\FedExOrderTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RefreshFedExShipmentTrackingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $shipmentId,
    ) {}

    public function handle(FedExOrderTrackingSyncService $sync): void
    {
        $shipment = Shipment::query()->with(['store', 'carrierAccount', 'order'])->find($this->shipmentId);
        if ($shipment === null || $shipment->store === null) {
            return;
        }

        if (! filled($shipment->tracking_number)) {
            return;
        }

        if (! filter_var(config('carriers.fedex.ops_tracking_enabled', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        try {
            $outcome = $sync->refreshShipment($shipment->store, $shipment);
        } catch (HttpException $e) {
            // Permanent capability / account issues should not retry forever.
            if (in_array($e->getStatusCode(), [404, 422], true)) {
                return;
            }

            throw $e;
        }

        $result = $outcome['result'] ?? null;
        if ($result && ! $result->success) {
            $httpStatus = (int) data_get($result->responseSummary, 'http_status');
            $code = strtolower((string) ($result->errorCode ?? ''));
            $retryable = $code === 'transport_error'
                || $httpStatus === 0
                || $httpStatus >= 500;

            if ($retryable) {
                throw new \RuntimeException(
                    'FedEx tracking refresh failed: '.($result->errorMessage ?: $code ?: 'unknown')
                );
            }
        }
        // Non-retryable FedEx business failures leave the shipment unchanged without failing the job.
    }
}
