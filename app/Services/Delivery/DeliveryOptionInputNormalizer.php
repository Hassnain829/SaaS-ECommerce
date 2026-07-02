<?php

namespace App\Services\Delivery;

use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryOptionInputNormalizer
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function applyPricingMode(?string $mode, array $validated): array
    {
        return match ($mode) {
            'free' => array_merge($validated, [
                'rate_type' => ShippingMethod::RATE_FREE,
                'flat_rate' => 0,
                'free_over_amount' => null,
            ]),
            'free_over' => array_merge($validated, [
                'rate_type' => ShippingMethod::RATE_FLAT,
                'flat_rate' => $validated['flat_rate'] ?? 0,
            ]),
            'fixed' => array_merge($validated, [
                'rate_type' => ShippingMethod::RATE_FLAT,
                'free_over_amount' => null,
            ]),
            default => array_merge($validated, [
                'rate_type' => $validated['rate_type'] ?? ShippingMethod::RATE_FLAT,
            ]),
        };
    }

    /**
     * Simple delivery-option availability used by wizard and structured create flows.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function applySimpleAvailability(Request $request, array $validated, ?ShippingMethod $existing): array
    {
        if ($existing === null) {
            $available = $request->boolean('available_to_customers', true);
            $validated['enabled_for_checkout'] = $available;
            $validated['is_active'] = $available;

            return $validated;
        }

        if ($existing->is_active !== $existing->enabled_for_checkout) {
            if ($request->input('resolve_flag_mismatch') === 'available') {
                $validated['enabled_for_checkout'] = true;
                $validated['is_active'] = true;
            } else {
                $validated['enabled_for_checkout'] = $existing->enabled_for_checkout;
                $validated['is_active'] = $existing->is_active;
            }

            return $validated;
        }

        $available = $request->boolean('available_to_customers', $existing->enabled_for_checkout);
        $validated['enabled_for_checkout'] = $available;
        $validated['is_active'] = $available;

        return $validated;
    }

    /**
     * Advanced editor availability (explicit toggles); preserves legacy flag mismatches.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function applyAdvancedAvailability(Request $request, array $validated, bool $isCreate, ?ShippingMethod $existing = null): array
    {
        if ($isCreate && $request->boolean('available_to_customers', true)) {
            $validated['enabled_for_checkout'] = true;
            $validated['is_active'] = true;

            return $validated;
        }

        if ($isCreate) {
            $available = $request->boolean('available_to_customers');
            $validated['enabled_for_checkout'] = $available;
            $validated['is_active'] = $available;

            return $validated;
        }

        $validated['enabled_for_checkout'] = $request->boolean('enabled_for_checkout');
        $validated['is_active'] = $request->boolean('is_active');

        if ($existing !== null
            && $existing->is_active !== $existing->enabled_for_checkout
            && $validated['enabled_for_checkout'] === $existing->enabled_for_checkout
            && $validated['is_active'] === $existing->is_active) {
            // Preserve mismatched legacy flags until merchant explicitly changes advanced toggles.
        }

        return $validated;
    }

    public function uniqueMethodCode(int $storeId, string $name): string
    {
        $base = Str::slug($name) ?: 'delivery-option';
        $code = $base;
        $suffix = 2;

        while (ShippingMethod::query()->where('store_id', $storeId)->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function assertValidPricingAndDays(string $priceMode, array $validated): array
    {
        if ($priceMode === 'fixed') {
            if (! array_key_exists('flat_rate', $validated) || $validated['flat_rate'] === null || $validated['flat_rate'] === '') {
                throw ValidationException::withMessages(['flat_rate' => 'Enter a delivery price for fixed pricing.']);
            }
            if ((float) $validated['flat_rate'] < 0) {
                throw ValidationException::withMessages(['flat_rate' => 'Delivery price cannot be negative.']);
            }
        }

        if ($priceMode === 'free_over') {
            if (! array_key_exists('free_over_amount', $validated) || (float) ($validated['free_over_amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['free_over_amount' => 'Enter the order amount required for free delivery.']);
            }
            if ((float) ($validated['flat_rate'] ?? 0) < 0) {
                throw ValidationException::withMessages(['flat_rate' => 'Delivery price cannot be negative.']);
            }
        }

        $minDays = $validated['estimated_min_days'] ?? null;
        $maxDays = $validated['estimated_max_days'] ?? null;
        if ($minDays !== null && $maxDays !== null && (int) $minDays > (int) $maxDays) {
            throw ValidationException::withMessages(['estimated_max_days' => 'Maximum days must be greater than or equal to minimum days.']);
        }

        return $validated;
    }

    /**
     * Preserve advanced fields not exposed in simple/wizard editors.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function mergePreservedMethodFields(ShippingMethod $existing, array $attributes): array
    {
        return array_merge($attributes, [
            'code' => $existing->code,
            'description' => $existing->description,
            'min_order_amount' => $existing->min_order_amount,
            'max_order_amount' => $existing->max_order_amount,
            'sort_order' => $existing->sort_order,
        ]);
    }
}
