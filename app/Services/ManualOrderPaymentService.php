<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualOrderPaymentService
{
    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CARD_IN_PERSON = 'card_in_person';

    public const METHOD_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_BANK_TRANSFER => 'Bank transfer',
            self::METHOD_CARD_IN_PERSON => 'Card (in person)',
            self::METHOD_OTHER => 'Other',
        ];
    }

    public static function methodLabel(string $method): string
    {
        return self::methods()[$method] ?? str($method)->replace('_', ' ')->title()->toString();
    }

    public function canRecord(Order $order): bool
    {
        if ($order->order_source !== 'manual') {
            return false;
        }

        if (in_array((string) $order->status, [
            OrderLifecycle::ORDER_CANCELLED,
            OrderLifecycle::ORDER_REFUNDED,
        ], true)) {
            return false;
        }

        if ((string) $order->payment_status !== OrderLifecycle::PAYMENT_PENDING) {
            return false;
        }

        if (filled($order->payment_gateway) && $order->payment_gateway !== 'manual') {
            return false;
        }

        if (data_get($order->meta, 'platform_checkout')) {
            return false;
        }

        return true;
    }

    public function record(Order $order, User $actor, string $method, ?string $reference = null): Order
    {
        $method = trim($method);
        $reference = trim((string) $reference);
        $reference = $reference !== '' ? $reference : null;

        if (! array_key_exists($method, self::methods())) {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose how this payment was collected.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $method, $reference): Order {
            /** @var Order $locked */
            $locked = Order::query()
                ->where('store_id', $order->store_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canRecord($locked)) {
                throw ValidationException::withMessages([
                    'payment' => $this->rejectionMessage($locked),
                ]);
            }

            $meta = is_array($locked->meta) ? $locked->meta : [];
            $meta['manual_payment'] = [
                'method' => $method,
                'recorded_at' => now()->toIso8601String(),
                'recorded_by' => $actor->id,
            ];

            $locked->forceFill([
                'payment_status' => OrderLifecycle::PAYMENT_PAID,
                'outstanding_total' => 0,
                'payment_method' => $method,
                'payment_gateway' => 'manual',
                'payment_reference' => $reference,
                'updated_by' => $actor->id,
                'meta' => $meta,
            ])->save();

            $label = self::methodLabel($method);
            $description = $reference
                ? 'Payment was recorded as '.$label.' ('.$reference.'). This did not charge a card through this workspace.'
                : 'Payment was recorded as '.$label.'. This did not charge a card through this workspace.';

            app(OrderEventRecorder::class)->record(
                $locked,
                OrderLifecycle::EVENT_PAYMENT_STATUS_RECORDED,
                'Payment recorded',
                $description,
                [
                    'from' => OrderLifecycle::PAYMENT_PENDING,
                    'to' => OrderLifecycle::PAYMENT_PAID,
                    'method' => $method,
                    'reference' => $reference,
                    'source' => 'manual_order',
                ],
                $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    private function rejectionMessage(Order $order): string
    {
        if ($order->order_source !== 'manual' || data_get($order->meta, 'platform_checkout')) {
            return 'Website checkout payments are recorded automatically when the customer pays.';
        }

        if ((string) $order->payment_status === OrderLifecycle::PAYMENT_PAID) {
            return 'Payment has already been recorded for this order.';
        }

        return 'Payment cannot be recorded for this order.';
    }
}
