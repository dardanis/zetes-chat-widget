<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use App\Services\Rag\PdfTextExtractorService;
use Throwable;

class PdfParser implements DocumentParserInterface
{
    public function __construct(private readonly PdfTextExtractorService $extractor) {}

    public function supports(string $extension, string $mimeType): bool
    {
        return $extension === 'pdf' || $mimeType === 'application/pdf';
    }

    public function parse(string $filePath, array $context = []): array
    {
        try {
            return array_map(static fn (array $page): array => [
                'text' => $page['text'],
                'metadata' => [
                    'original_name' => $context['original_name'] ?? null,
                    'file_type' => 'pdf',
                    'page_number' => $page['page'],
                ],
            ], $this->extractor->extractByPage($filePath));
        } catch (Throwable $exception) {
            throw new DocumentParseException('Unable to extract text from PDF.', previous: $exception);
        }
    }
}
