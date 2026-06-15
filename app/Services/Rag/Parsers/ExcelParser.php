<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ExcelParser implements DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool
    {
        return in_array($extension, ['xlsx', 'xls'], true)
            || in_array($mimeType, [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
            ], true);
    }

    public function parse(string $filePath, array $context = []): array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (Throwable $exception) {
            throw new DocumentParseException('Unable to read spreadsheet document.', previous: $exception);
        }

        $units = [];
        $sheetIndex = 0;
        $maxSheets = max(1, (int) config('rag.parsers.excel.max_sheets', 20));
        $maxRows = max(1, (int) config('rag.parsers.excel.max_rows_per_sheet', 5000));
        $maxColumns = max(1, (int) config('rag.parsers.excel.max_columns_per_sheet', 100));
        $rowsPerUnit = max(1, (int) config('rag.parsers.excel.rows_per_chunk', 20));
        $extension = strtolower((string) ($context['extension'] ?? pathinfo($filePath, PATHINFO_EXTENSION)));

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetIndex++;

            if ($sheetIndex > $maxSheets) {
                break;
            }

            $highestRow = min($worksheet->getHighestDataRow(), $maxRows);
            $highestColumnIndex = min(Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()), $maxColumns);

            if ($highestRow < 1 || $highestColumnIndex < 1) {
                continue;
            }

            $rows = [];

            for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
                $row = [];

                for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                    $value = $worksheet->getCell([$columnIndex, $rowNumber])->getFormattedValue();
                    $row[] = trim((string) $value);
                }

                if (array_filter($row, static fn (string $value): bool => $value !== '') === []) {
                    continue;
                }

                $rows[$rowNumber] = $row;
            }

            if ($rows === []) {
                continue;
            }

            $firstRowNumber = array_key_first($rows);
            $headers = $this->looksLikeHeader($rows[$firstRowNumber])
                ? array_map(static fn (string $header, int $index): string => $header !== '' ? $header : 'Column '.($index + 1), $rows[$firstRowNumber], array_keys($rows[$firstRowNumber]))
                : null;

            if ($headers !== null) {
                unset($rows[$firstRowNumber]);
            }

            foreach (array_chunk($rows, $rowsPerUnit, true) as $rowChunk) {
                $lines = [];
                $rowNumbers = [];

                foreach ($rowChunk as $rowNumber => $row) {
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
                        'file_type' => $extension ?: 'xlsx',
                        'sheet_name' => $worksheet->getTitle(),
                        'row_range' => min($rowNumbers).'-'.max($rowNumbers),
                    ],
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        return $units;
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
