<?php

namespace App\Services\Rag\Parsers;

use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class DocxParser implements DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool
    {
        return $extension === 'docx'
            || $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function parse(string $filePath, array $context = []): array
    {
        try {
            $phpWord = IOFactory::load($filePath, 'Word2007');
        } catch (Throwable $exception) {
            throw new DocumentParseException('Unable to read DOCX document.', previous: $exception);
        }

        $units = [];
        $sectionNumber = 0;
        $maxChars = max(1000, (int) config('rag.parsers.max_chars_per_parsed_unit', 12000));

        foreach ($phpWord->getSections() as $section) {
            $sectionNumber++;
            $lines = [];

            foreach ($section->getElements() as $element) {
                $this->appendElementText($element, $lines);
            }

            $text = trim(implode("\n", array_filter($lines, static fn (string $line): bool => trim($line) !== '')));

            if ($text === '') {
                continue;
            }

            foreach (str_split($text, $maxChars) as $partIndex => $part) {
                $units[] = [
                    'text' => trim($part),
                    'metadata' => [
                        'original_name' => $context['original_name'] ?? null,
                        'file_type' => 'docx',
                        'section' => 'section-'.$sectionNumber.'-part-'.($partIndex + 1),
                    ],
                ];
            }
        }

        return $units;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendElementText(AbstractElement $element, array &$lines): void
    {
        if ($element instanceof Text || $element instanceof Title || $element instanceof Link) {
            $text = trim((string) $element->getText());

            if ($text !== '') {
                $lines[] = $text;
            }

            return;
        }

        if ($element instanceof ListItem) {
            $textObject = $element->getTextObject();

            if ($textObject instanceof AbstractElement) {
                $nested = [];
                $this->appendElementText($textObject, $nested);

                if ($nested !== []) {
                    $lines[] = '- '.implode(' ', $nested);
                }
            }

            return;
        }

        if ($element instanceof Table) {
            $this->appendTableText($element, $lines);

            return;
        }

        if ($element instanceof TextRun || $element instanceof AbstractContainer) {
            $nested = [];

            foreach ($element->getElements() as $child) {
                if ($child instanceof AbstractElement) {
                    $this->appendElementText($child, $nested);
                }
            }

            $text = trim(implode(' ', $nested));

            if ($text !== '') {
                $lines[] = $text;
            }
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendTableText(Table $table, array &$lines): void
    {
        foreach ($table->getRows() as $rowIndex => $row) {
            if (! $row instanceof Row) {
                continue;
            }

            $cells = [];

            foreach ($row->getCells() as $cell) {
                if (! $cell instanceof Cell) {
                    continue;
                }

                $cellLines = [];

                foreach ($cell->getElements() as $element) {
                    if ($element instanceof AbstractElement) {
                        $this->appendElementText($element, $cellLines);
                    }
                }

                $cells[] = trim(implode(' ', $cellLines));
            }

            $rowText = trim(implode(' | ', array_filter($cells, static fn (string $cell): bool => $cell !== '')));

            if ($rowText !== '') {
                $lines[] = 'Table row '.($rowIndex + 1).': '.$rowText;
            }
        }
    }
}
