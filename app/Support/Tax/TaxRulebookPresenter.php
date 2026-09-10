<?php

namespace App\Support\Tax;

use App\Models\TaxRate;
use App\Models\TaxSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class TaxRulebookPresenter
{
    /**
     * @param  Collection<int, TaxRate>  $taxRates
     * @return array<string, mixed>
     */
    public static function pageState(
        Collection $taxRates,
        TaxSetting $settings,
        string $requestedSection,
        int $requestedRateId,
        int $editingRateId,
        bool $openCreateRateForm,
    ): array {
        $section = in_array($requestedSection, ['behavior', 'jurisdictions', 'legal'], true)
            ? $requestedSection
            : 'jurisdictions';

        if ($openCreateRateForm || $editingRateId > 0) {
            $section = 'jurisdictions';
        }

        $groups = self::countryGroups($taxRates);
        $rateIds = $taxRates->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $selectedRateId = $editingRateId > 0 && in_array($editingRateId, $rateIds, true)
            ? $editingRateId
            : ($requestedRateId > 0 && in_array($requestedRateId, $rateIds, true)
                ? $requestedRateId
                : (int) ($taxRates->first()?->id ?? 0));

        $activeRatesCount = $taxRates->where('is_active', true)->count();
        $countryCount = count($groups);

        return [
            'rulebookSection' => $section,
            'selectedRateId' => $selectedRateId,
            'countryGroups' => $groups,
            'countryCount' => $countryCount,
            'activeRatesCount' => $activeRatesCount,
            'lastSavedLabel' => self::formatSavedAt($settings->updated_at),
        ];
    }

    /**
     * @param  Collection<int, TaxRate>  $taxRates
     * @return list<array<string, mixed>>
     */
    public static function countryGroups(Collection $taxRates): array
    {
        $grouped = [];

        foreach ($taxRates as $rate) {
            $code = strtoupper((string) $rate->country_code);
            if ($code === '') {
                $code = 'ZZ';
            }

            if (! isset($grouped[$code])) {
                $grouped[$code] = [
                    'code' => $code,
                    'name' => TaxCountryCatalog::name($code),
                    'flag' => self::flagEmoji($code),
                    'rates' => [],
                ];
            }

            $summary = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code);
            $scopeLabel = $summary['scope'] === 'country-wide'
                ? 'Country-wide'
                : (string) ($summary['region_label'] ?? strtoupper((string) $rate->region_code));
            $search = strtolower(implode(' ', array_filter([
                $grouped[$code]['name'],
                $code,
                $scopeLabel,
                $summary['scope'],
                $rate->name,
                $rate->region_code,
                (string) $rate->rate_percent,
            ])));

            $grouped[$code]['rates'][] = [
                'model' => $rate,
                'summary' => $summary,
                'scopeLabel' => $scopeLabel,
                'search' => $search,
            ];
        }

        uasort($grouped, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        foreach ($grouped as &$group) {
            $total = count($group['rates']);
            $active = collect($group['rates'])->filter(
                static fn (array $entry): bool => (bool) $entry['model']->is_active
            )->count();
            $group['total'] = $total;
            $group['active'] = $active;
            $group['status'] = $active === $total ? 'Active' : ($active === 0 ? 'Inactive' : 'Mixed');
        }
        unset($group);

        return array_values($grouped);
    }

    public static function flagEmoji(string $countryCode): string
    {
        $code = strtoupper(trim($countryCode));
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }

        $chars = str_split($code);

        return implode('', array_map(
            static fn (string $char): string => mb_chr(0x1F1E6 + ord($char) - ord('A'), 'UTF-8'),
            $chars
        ));
    }

    public static function formatSavedAt(mixed $at): string
    {
        if (! $at instanceof CarbonInterface) {
            return 'Not saved yet';
        }

        if ($at->greaterThanOrEqualTo(now()->subMinute())) {
            return 'Just now';
        }

        return $at->diffForHumans();
    }
}
