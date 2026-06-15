<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use JsonException;

class JsonParser implements DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool
    {
        return $extension === 'json'
            || in_array($mimeType, ['application/json', 'text/json', 'text/plain'], true);
    }

    public function parse(string $filePath, array $context = []): array
    {
        $json = file_get_contents($filePath);

        if ($json === false) {
            throw new DocumentParseException('Unable to read JSON document.');
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DocumentParseException('Uploaded JSON document is invalid.', previous: $exception);
        }

        $lines = [];
        $this->flatten($data, '', $lines);

        return $this->splitLines($lines, [
            'original_name' => $context['original_name'] ?? null,
            'file_type' => 'json',
        ]);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function flatten(mixed $value, string $path, array &$lines): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $childPath = $path === ''
                    ? (string) $key
                    : (is_int($key) ? "{$path}[{$key}]" : "{$path}.{$key}");

                $this->flatten($child, $childPath, $lines);
            }

            return;
        }

        $rendered = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => (string) $value,
        };

        $lines[] = ($path !== '' ? $path : 'value').': '.$rendered;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{text:string, metadata:array<string, mixed>}>
     */
    private function splitLines(array $lines, array $metadata): array
    {
        $maxChars = max(1000, (int) config('rag.parsers.max_chars_per_parsed_unit', 12000));
        $units = [];
        $current = [];
        $currentLength = 0;
        $firstPath = null;

        foreach ($lines as $line) {
            $lineLength = mb_strlen($line) + 1;

            if ($current !== [] && $currentLength + $lineLength > $maxChars) {
                $units[] = $this->makeUnit($current, $metadata, $firstPath);
                $current = [];
                $currentLength = 0;
                $firstPath = null;
            }

            $current[] = $line;
            $currentLength += $lineLength;
            $firstPath ??= strtok($line, ':') ?: null;
        }

        if ($current !== []) {
            $units[] = $this->makeUnit($current, $metadata, $firstPath);
        }

        return $units;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, mixed>  $metadata
     * @return array{text:string, metadata:array<string, mixed>}
     */
    private function makeUnit(array $lines, array $metadata, ?string $firstPath): array
    {
        return [
            'text' => implode("\n", $lines),
            'metadata' => array_filter(array_merge($metadata, [
                'json_path' => $firstPath,
            ]), static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }
}
