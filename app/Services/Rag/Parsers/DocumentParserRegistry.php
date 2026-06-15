<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\UnsupportedDocumentTypeException;

class DocumentParserRegistry
{
    /**
     * @var array<int, DocumentParserInterface>
     */
    private array $parsers;

    public function __construct(
        PdfParser $pdfParser,
        TextParser $textParser,
        CsvParser $csvParser,
        ExcelParser $excelParser,
        DocxParser $docxParser,
        HtmlParser $htmlParser,
        JsonParser $jsonParser,
        XmlParser $xmlParser,
    ) {
        $this->parsers = [
            $pdfParser,
            $textParser,
            $csvParser,
            $excelParser,
            $docxParser,
            $htmlParser,
            $jsonParser,
            $xmlParser,
        ];
    }

    public function resolve(string $extension, string $mimeType): DocumentParserInterface
    {
        $normalizedExtension = strtolower(ltrim($extension, '.'));
        $normalizedMimeType = strtolower($mimeType);

        foreach ($this->parsers as $parser) {
            if ($parser->supports($normalizedExtension, $normalizedMimeType)) {
                return $parser;
            }
        }

        throw new UnsupportedDocumentTypeException("Unsupported document type [{$normalizedExtension}] with MIME [{$mimeType}].");
    }
}
