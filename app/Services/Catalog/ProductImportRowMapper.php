<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;

final class ProductImportRowMapper
{
    /**
     * @param  list<string>  $headers
     * @param  list<string>  $cells
     * @return array<string, string>
     */
    public function cellsToKeyedRow(array $headers, array $cells): array
    {
        $row = [];
        foreach ($headers as $i => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $cells[$i] ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    public function extractMappedFields(array $row, array $mapping): array
    {
        $allowed = array_flip(array_keys(ProductImportField::labels()));
        $out = [];
        foreach ($mapping as $field => $sourceHeader) {
            if (! isset($allowed[$field])) {
                continue;
            }
            if (! is_string($sourceHeader) || $sourceHeader === '') {
                continue;
            }
            $out[$field] = $row[$sourceHeader] ?? '';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     * @param  array<string, string>  $mapping
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array<string, string>
     */
    public function collectUnmappedExtras(array $row, array $headers, array $mapping, array $customMappings): array
    {
        $used = array_filter(array_values($mapping), static fn ($h) => is_string($h) && $h !== '');
        foreach ($customMappings as $cm) {
            $used[] = $cm['source'];
        }
        $used = array_values(array_unique($used));
        $extras = [];
        foreach ($headers as $h) {
            if ($h === '' || in_array($h, $used, true)) {
                continue;
            }
            $val = trim((string) ($row[$h] ?? ''));
            if ($val !== '') {
                $extras[$h] = $val;
            }
        }

        return $extras;
    }

    /**
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public function extractCustomFieldValues(array $row, array $customMappings): array
    {
        $product = [];
        $variant = [];
        foreach ($customMappings as $map) {
            $src = $map['source'];
            $key = $map['key'];
            $scope = $map['scope'];
            $val = trim((string) ($row[$src] ?? ''));
            if ($val === '') {
                continue;
            }
            if ($scope === 'attribute') {
                continue;
            }
            if ($scope === 'variant') {
                $variant[$key] = $val;
            } else {
                $product[$key] = $val;
            }
        }

        return [$product, $variant];
    }

    /**
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array<string, list<string>>
     */
    public function extractAttributeValues(array $row, array $customMappings): array
    {
        $attributes = [];
        foreach ($customMappings as $map) {
            if (($map['scope'] ?? '') !== 'attribute') {
                continue;
            }

            $key = trim((string) ($map['key'] ?? ''));
            $source = (string) ($map['source'] ?? '');
            if ($key === '' || $source === '') {
                continue;
            }

            foreach ($this->splitDelimited((string) ($row[$source] ?? '')) as $value) {
                if ($value !== '') {
                    $attributes[$key][] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    public function splitDelimited(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        foreach (['|', ';', "\n"] as $delim) {
            if (str_contains($value, $delim)) {
                return array_values(array_filter(array_map('trim', explode($delim, $value))));
            }
        }
        if (str_contains($value, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [$value];
    }
}
