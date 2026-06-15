<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use DOMDocument;
use DOMElement;
use DOMNode;
use Throwable;

class XmlParser implements DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool
    {
        return $extension === 'xml'
            || in_array($mimeType, ['application/xml', 'text/xml'], true)
            || str_ends_with($mimeType, '+xml');
    }

    public function parse(string $filePath, array $context = []): array
    {
        $xml = file_get_contents($filePath);

        if ($xml === false) {
            throw new DocumentParseException('Unable to read XML document.');
        }

        try {
            libxml_use_internal_errors(true);
            $document = new DOMDocument;
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
        } catch (Throwable $exception) {
            throw new DocumentParseException('Unable to parse XML document.', previous: $exception);
        }

        if (! $loaded || ! $document->documentElement instanceof DOMElement) {
            throw new DocumentParseException('Uploaded XML document is invalid.');
        }

        $lines = [];
        $this->flatten($document->documentElement, $document->documentElement->nodeName, $lines);

        return $this->splitLines($lines, [
            'original_name' => $context['original_name'] ?? null,
            'file_type' => 'xml',
        ]);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function flatten(DOMElement $node, string $path, array &$lines): void
    {
        foreach ($node->attributes ?? [] as $attribute) {
            $lines[] = "{$path}.@{$attribute->nodeName}: ".trim($attribute->nodeValue ?? '');
        }

        $children = array_values(array_filter(
            iterator_to_array($node->childNodes),
            static fn (DOMNode $child): bool => $child instanceof DOMElement,
        ));

        if ($children === []) {
            $value = trim($node->textContent);

            if ($value !== '') {
                $lines[] = "{$path}: {$value}";
            }

            return;
        }

        foreach ($children as $child) {
            $this->flatten($child, "{$path}.{$child->nodeName}", $lines);
        }
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
                'xml_path' => $firstPath,
            ]), static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }
}
