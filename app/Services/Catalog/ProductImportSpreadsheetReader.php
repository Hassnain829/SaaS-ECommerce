<?php

namespace App\Services\Catalog;

use App\Support\Catalog\ProductImportHeaderNormalizer;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

final class ProductImportSpreadsheetReader
{
    /**
     * @return list<string> trimmed header labels (empty cells preserved as "")
     */
    public function readHeaderRow(string $absolutePath, string $extension): array
    {
        $reader = $this->makeReader($extension);
        $reader->open($absolutePath);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return ProductImportHeaderNormalizer::sanitizeHeaderRow($this->rowToStringList($row));
                }
            }
        } finally {
            $reader->close();
        }

        return [];
    }

    /**
     * @param  callable(list<string>): void  $onRow  zero-based data rows (excluding header)
     */
    public function eachDataRow(string $absolutePath, string $extension, callable $onRow): void
    {
        $reader = $this->makeReader($extension);
        $reader->open($absolutePath);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $isFirst = true;
                foreach ($sheet->getRowIterator() as $row) {
                    if ($isFirst) {
                        $isFirst = false;

                        continue;
                    }
                    $onRow($this->rowToStringList($row));
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Stream data rows in fixed-size chunks (header row excluded) without loading the full sheet into memory.
     *
     * @param  callable(list<list<string>>): bool  $onChunk  return false to stop reading early (e.g. row cap)
     */
    public function eachDataRowChunk(string $absolutePath, string $extension, int $chunkSize, callable $onChunk): void
    {
        $chunkSize = max(1, $chunkSize);
        $reader = $this->makeReader($extension);
        $reader->open($absolutePath);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $isFirst = true;
                /** @var list<list<string>> $buffer */
                $buffer = [];
                foreach ($sheet->getRowIterator() as $row) {
                    if ($isFirst) {
                        $isFirst = false;

                        continue;
                    }
                    $buffer[] = $this->rowToStringList($row);
                    if (count($buffer) >= $chunkSize) {
                        if ($onChunk($buffer) === false) {
                            return;
                        }
                        $buffer = [];
                    }
                }

                if ($buffer !== [] && $onChunk($buffer) === false) {
                    return;
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Full scan of data rows (excludes header). Used for import progress totals.
     */
    public function countDataRows(string $absolutePath, string $extension): int
    {
        $count = 0;
        $this->eachDataRow($absolutePath, $extension, static function () use (&$count): void {
            $count++;
        });

        return $count;
    }

    private function makeReader(string $extension): ReaderInterface
    {
        $ext = strtolower($extension);

        return match ($ext) {
            'csv', 'txt' => new CsvReader,
            'xlsx' => new XlsxReader,
            default => throw new \InvalidArgumentException('Unsupported import file type: '.$extension),
        };
    }

    /**
     * @return list<string>
     */
    private function rowToStringList(Row $row): array
    {
        $out = [];
        foreach ($row->getCells() as $cell) {
            $out[] = $this->cellToString($cell);
        }

        return $out;
    }

    private function cellToString(Cell $cell): string
    {
        $v = $cell->getValue();
        if ($v === null) {
            return '';
        }
        if (is_float($v) || is_int($v)) {
            return is_float($v) && floor($v) == $v
                ? (string) (int) $v
                : rtrim(rtrim((string) $v, '0'), '.');
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }

        return trim((string) $v);
    }
}
