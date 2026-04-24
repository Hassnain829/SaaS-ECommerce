<?php

namespace App\Support;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Human-readable variant labels for merchant UI (Day 15).
 */
final class ProductVariantLabel
{
    /**
     * @param  Collection<int, \App\Models\ProductVariationOption>  $options
     * @return array{label: string, short: string, chips: list<array{group: string, value: string}>}
     */
    public static function fromOptions(Collection $options): array
    {
        $chips = [];
        foreach ($options as $opt) {
            $group = 'Option';
            if ($opt->relationLoaded('variationType') && $opt->variationType) {
                $group = (string) $opt->variationType->name;
            }
            $chips[] = [
                'group' => $group,
                'value' => (string) $opt->value,
            ];
        }

        $long = collect($chips)->map(fn (array $c): string => $c['group'].': '.$c['value'])->implode(' · ');
        $short = collect($chips)->pluck('value')->implode(' · ');

        return [
            'label' => $long !== '' ? $long : 'Default variant',
            'short' => $short !== '' ? $short : 'Default variant',
            'chips' => $chips,
        ];
    }

    public static function forVariant(ProductVariant $variant, int $variantIndex, int $variantCount): string
    {
        $options = $variant->relationLoaded('options')
            ? $variant->options->sortBy(fn ($o) => [$o->variation_type_id, $o->sort_order])->values()
            : collect();

        $parsed = self::fromOptions($options);

        if ($parsed['label'] !== 'Default variant') {
            return $parsed['label'];
        }

        return $variantCount > 1 ? 'Variant '.($variantIndex + 1) : 'Default variant';
    }
}
