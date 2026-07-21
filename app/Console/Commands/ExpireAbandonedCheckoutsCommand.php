<?php

namespace App\Console\Commands;

use App\Models\Checkout;
use App\Models\InventoryReservation;
use App\Models\PaymentIntent;
use App\Services\CheckoutEventRecorder;
use App\Services\Coupons\CouponService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireAbandonedCheckoutsCommand extends Command
{
    protected $signature = 'checkouts:expire-abandoned {--limit=100 : Max checkouts to process per run}';

    protected $description = 'Cancel expired platform checkouts and release reserved inventory and coupons';

    public function handle(
        CouponService $couponService,
        InventoryReservationService $reservationService,
        CheckoutEventRecorder $eventRecorder,
        PaymentProviderManager $paymentProviderManager,
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        Checkout::query()
            ->where('status', Checkout::STATUS_PAYMENT_PENDING)
            ->whereNull('converted_order_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now('UTC'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Checkout $checkout) use (
                &$processed,
                $couponService,
                $reservationService,
                $eventRecorder,
                $paymentProviderManager,
            ): void {
                DB::transaction(function () use (
                    $checkout,
                    &$processed,
                    $couponService,
                    $reservationService,
                    $eventRecorder,
                    $paymentProviderManager,
                ): void {
                    $locked = Checkout::query()
                        ->whereKey($checkout->id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        ! $locked
                        || $locked->status !== Checkout::STATUS_PAYMENT_PENDING
                        || $locked->converted_order_id
                        || ! $locked->expires_at
                        || $locked->expires_at->isFuture()
                    ) {
                        return;
                    }

                    $reservations = InventoryReservation::query()
                        ->where('store_id', $locked->store_id)
                        ->where('reference_type', 'checkout')
                        ->where('reference_id', (string) $locked->id)
                        ->whereIn('status', [InventoryReservation::STATUS_ACTIVE, InventoryReservation::STATUS_COMMITTED])
                        ->get();

                    foreach ($reservations as $reservation) {
                        $reservationService->release($reservation, [
                            'source' => 'platform_checkout',
                            'reference_type' => 'checkout',
                            'reference_id' => $locked->id,
                            'reference_code' => $locked->checkout_number,
                        ]);
                    }

                    $couponService->release($locked);

                    $paymentIntent = PaymentIntent::query()
                        ->where('checkout_id', $locked->id)
                        ->whereNull('order_id')
                        ->latest('id')
                        ->first();

                    if (
                        $paymentIntent?->provider_intent_id
                        && $locked->payment_provider
                        && in_array((string) $paymentIntent->status, ['requires_payment_method', 'requires_confirmation'], true)
                    ) {
                        try {
                            $paymentProviderManager
                                ->driver((string) $locked->payment_provider)
                                ->cancelPaymentIntent((string) $paymentIntent->provider_intent_id, [
                                    'mode' => $paymentIntent->mode,
                                ]);
                            $paymentIntent->forceFill([
                                'status' => 'canceled',
                                'failed_at' => now('UTC'),
                            ])->save();
                        } catch (\Throwable) {
                            // Expiry must still complete even if Stripe cancel is unavailable.
                        }
                    }

                    $locked->forceFill([
                        'status' => Checkout::STATUS_CANCELLED,
                    ])->save();

                    $eventRecorder->record(
                        $locked,
                        'checkout.expired',
                        'Checkout expired',
                        'This unpaid checkout expired, so reserved stock and coupons were released.',
                        [
                            'expires_at' => optional($locked->expires_at)?->toIso8601String(),
                            'reservation_count' => $reservations->count(),
                        ],
                    );

                    $processed++;
                });
            });

        $this->info("Expired {$processed} abandoned checkout(s).");

        return self::SUCCESS;
    }
}
