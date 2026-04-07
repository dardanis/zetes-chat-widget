<?php

namespace App\Services\Rag;

use Smalot\PdfParser\Parser;

class PdfTextExtractorService
{
    /**
     * @return array<int, array{page:int, text:string}>
     */
    public function extractByPage(string $absolutePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($absolutePath);

        $pages = [];

        foreach ($pdf->getPages() as $index => $page) {
            $text = preg_replace('/\s+/u', ' ', trim($page->getText())) ?? '';

            if ($text === '') {
                continue;
            }

            $pages[] = [
                'page' => $index + 1,
                'text' => $text,
            ];
        }

        return $pages;
    }
}

