<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use DOMDocument;
use DOMXPath;
use Throwable;

class HtmlParser implements DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool
    {
        return $extension === 'html'
            || in_array($mimeType, ['text/html', 'application/xhtml+xml'], true);
    }

    public function parse(string $filePath, array $context = []): array
    {
        $html = file_get_contents($filePath);

        if ($html === false) {
            throw new DocumentParseException('Unable to read HTML document.');
        }

        try {
            $document = new DOMDocument;
            libxml_use_internal_errors(true);
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            $xpath = new DOMXPath($document);

            foreach ($xpath->query('//script|//style|//nav|//footer|//noscript|//svg|//form') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }

            $title = trim((string) ($xpath->query('//title')?->item(0)?->textContent ?? ''));
            $body = $xpath->query('//body')?->item(0);
            $text = trim(preg_replace('/\s+/u', ' ', $body?->textContent ?? $document->textContent) ?? '');
        } catch (Throwable $exception) {
            throw new DocumentParseException('Unable to parse HTML document.', previous: $exception);
        }

        return $this->splitText($text, [
            'original_name' => $context['original_name'] ?? null,
            'file_type' => 'html',
            'title' => $title !== '' ? $title : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{text:string, metadata:array<string, mixed>}>
     */
    private function splitText(string $text, array $metadata): array
    {
        if ($text === '') {
            return [];
        }

        $maxChars = max(1000, (int) config('rag.parsers.max_chars_per_parsed_unit', 12000));
        $units = [];

        foreach (str_split($text, $maxChars) as $index => $part) {
            $units[] = [
                'text' => trim($part),
                'metadata' => array_filter(array_merge($metadata, [
                    'section' => 'html-part-'.($index + 1),
                ]), static fn (mixed $value): bool => $value !== null && $value !== ''),
            ];
        }

        return $units;
    }
}
