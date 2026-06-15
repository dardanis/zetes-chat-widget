<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use SplFileObject;
use Throwable;

class CsvParser implements DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool
    {
        return $extension === 'csv'
            || in_array($mimeType, ['text/csv', 'application/csv', 'text/plain'], true);
    }

    public function parse(string $filePath, array $context = []): array
    {
        try {
            $delimiter = $this->detectDelimiter($filePath);
            $file = new SplFileObject($filePath);
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
            $file->setCsvControl($delimiter);

            $rows = [];

            foreach ($file as $row) {
                if (! is_array($row) || $row === [null]) {
                    continue;
                }

                $rows[] = array_map(static fn (mixed $value): string => trim((string) $value), $row);
            }
        } catch (Throwable $exception) {
            throw new DocumentParseException('Unable to read CSV document.', previous: $exception);
        }

        if ($rows === []) {
            return [];
        }

        $headers = $this->looksLikeHeader($rows[0])
            ? array_map(static fn (string $header, int $index): string => $header !== '' ? $header : 'Column '.($index + 1), $rows[0], array_keys($rows[0]))
            : null;

        $dataRows = $headers === null ? $rows : array_slice($rows, 1);
        $units = [];
        $rowsPerUnit = max(1, (int) config('rag.parsers.csv.rows_per_chunk', 50));

        foreach (array_chunk($dataRows, $rowsPerUnit, true) as $rowChunk) {
            $lines = [];
            $rowNumbers = [];

            foreach ($rowChunk as $offset => $row) {
                $rowNumber = $headers === null ? $offset + 1 : $offset + 2;
                $rowNumbers[] = $rowNumber;
                $lines[] = 'Row '.$rowNumber;

                foreach ($row as $columnIndex => $value) {
                    if ($value === '') {
                        continue;
                    }

                    $label = $headers[$columnIndex] ?? 'Column '.($columnIndex + 1);
                    $lines[] = $label.': '.$value;
                }
            }

            if ($lines === []) {
                continue;
            }

            $units[] = [
                'text' => implode("\n", $lines),
                'metadata' => [
                    'original_name' => $context['original_name'] ?? null,
                    'file_type' => 'csv',
                    'row_range' => min($rowNumbers).'-'.max($rowNumbers),
                ],
            ];
        }

        return $units;
    }

    private function detectDelimiter(string $filePath): string
    {
        $sample = (string) file_get_contents($filePath, false, null, 0, 4096);
        $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($delimiters) as $delimiter) {
            $delimiters[$delimiter] = substr_count($sample, $delimiter);
        }

        arsort($delimiters);

        return (string) array_key_first($delimiters);
    }

    /**
     * @param  array<int, string>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        $nonEmpty = array_values(array_filter($row, static fn (string $value): bool => $value !== ''));

        if ($nonEmpty === []) {
            return false;
        }

        foreach ($nonEmpty as $value) {
            if (is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
