<?php

namespace App\Services\Rag\Parsers;

class ParsedDocumentAdapter
{
    /**
     * @param  array<int, array{text:string, metadata:array<string, mixed>}>  $units
     * @return array<int, array{page:int, text:string}>
     */
    public function toChunkInput(array $units): array
    {
        return array_values(array_map(
            static fn (array $unit, int $index): array => [
                'page' => (int) data_get($unit, 'metadata.page_number', $index + 1),
                'text' => (string) $unit['text'],
            ],
            $units,
            array_keys($units),
        ));
    }

    /**
     * @param  array<int, array{text:string, metadata:array<string, mixed>}>  $units
     * @return array<string, mixed>
     */
    public function metadataForRange(array $units, int $from, int $to): array
    {
        $matched = array_values(array_filter($units, static function (array $unit, int $index) use ($from, $to): bool {
            $sourceNumber = (int) data_get($unit, 'metadata.page_number', $index + 1);

            return $sourceNumber >= $from && $sourceNumber <= $to;
        }, ARRAY_FILTER_USE_BOTH));

        if ($matched === []) {
            return [];
        }

        $metadata = [
            'source_units' => count($matched),
        ];

        foreach (['file_type', 'original_name', 'sheet_name', 'section', 'row_number', 'row_range', 'json_path', 'xml_path', 'title'] as $key) {
            $values = array_values(array_unique(array_filter(array_map(
                static fn (array $unit): mixed => data_get($unit, 'metadata.'.$key),
                $matched,
            ), static fn (mixed $value): bool => $value !== null && $value !== '')));

            if (count($values) === 1) {
                $metadata[$key] = $values[0];
            } elseif (count($values) > 1) {
                $metadata[$key.'s'] = $values;
            }
        }

        return $metadata;
    }
}
