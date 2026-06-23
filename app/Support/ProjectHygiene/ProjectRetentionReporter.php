<?php

namespace App\Support\ProjectHygiene;

final class ProjectRetentionReporter
{
    public function __construct(
        private readonly ProjectRetentionService $retention,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(?string $category = null): array
    {
        $categories = $category === null || $category === 'all'
            ? null
            : [$category];

        $scan = $this->retention->scan($categories);

        return [
            'mode' => 'dry-run',
            'categories' => $scan['categories'],
            'configured_retention' => $scan['configured_retention'],
            'summary' => $scan['summary'],
            'entries' => collect($scan['entries'])
                ->groupBy('category')
                ->map(fn ($items) => [
                    'items' => $items->values()->all(),
                    'summary' => $this->summarizeCategory($items->all()),
                ])
                ->all(),
            'classification_catalog' => ProjectStorageClassification::catalog(),
        ];
    }

    /**
     * @param  list<array{status: string, bytes: int}>  $entries
     * @return array<string, int>
     */
    private function summarizeCategory(array $entries): array
    {
        $summary = [
            'total_count' => count($entries),
            'total_bytes' => 0,
            'eligible_count' => 0,
            'eligible_bytes' => 0,
            'protected_count' => 0,
            'skipped_count' => 0,
        ];

        foreach ($entries as $entry) {
            $summary['total_bytes'] += $entry['bytes'];
            if ($entry['status'] === 'eligible') {
                $summary['eligible_count']++;
                $summary['eligible_bytes'] += $entry['bytes'];
            } elseif ($entry['status'] === 'protected') {
                $summary['protected_count']++;
            } else {
                $summary['skipped_count']++;
            }
        }

        return $summary;
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
