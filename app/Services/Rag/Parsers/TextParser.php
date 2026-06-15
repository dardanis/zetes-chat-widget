<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;

class TextParser implements DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool
    {
        return in_array($extension, ['txt', 'md'], true);
    }

    public function parse(string $filePath, array $context = []): array
    {
        $text = file_get_contents($filePath);

        if ($text === false) {
            throw new DocumentParseException('Unable to read text document.');
        }

        $text = $this->normalizeText($text);
        $extension = strtolower((string) ($context['extension'] ?? pathinfo($filePath, PATHINFO_EXTENSION)));

        if ($extension === 'md') {
            $sections = $this->parseMarkdownSections($text, $context);

            if ($sections !== []) {
                return $sections;
            }
        }

        return $text === '' ? [] : [[
            'text' => $text,
            'metadata' => [
                'original_name' => $context['original_name'] ?? null,
                'file_type' => $extension ?: 'txt',
            ],
        ]];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array{text:string, metadata:array<string, mixed>}>
     */
    private function parseMarkdownSections(string $text, array $context): array
    {
        preg_match_all('/^#{1,6}\s+(.+)$/m', $text, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return [];
        }

        $sections = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $start = $matches[0][$index][1];
            $end = $matches[0][$index + 1][1] ?? strlen($text);
            $sectionText = trim(substr($text, $start, $end - $start));

            if ($sectionText === '') {
                continue;
            }

            $sections[] = [
                'text' => $sectionText,
                'metadata' => [
                    'original_name' => $context['original_name'] ?? null,
                    'file_type' => 'md',
                    'section' => trim($matches[1][$index][0]),
                ],
            ];
        }

        return $sections;
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim(preg_replace("/[ \t]+/", ' ', $text) ?? $text);
    }
}
