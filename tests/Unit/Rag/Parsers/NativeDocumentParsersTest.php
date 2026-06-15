<?php

namespace Tests\Unit\Rag\Parsers;

use App\Services\Rag\Parsers\CsvParser;
use App\Services\Rag\Parsers\DocxParser;
use App\Services\Rag\Parsers\ExcelParser;
use App\Services\Rag\Parsers\HtmlParser;
use App\Services\Rag\Parsers\JsonParser;
use App\Services\Rag\Parsers\TextParser;
use App\Services\Rag\Parsers\XmlParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class NativeDocumentParsersTest extends TestCase
{
    public function test_text_parser_extracts_markdown_sections(): void
    {
        $path = $this->writeTempFile("# Intro\nWelcome.\n\n## Setup\nInstall it.");

        $units = (new TextParser)->parse($path, [
            'extension' => 'md',
            'original_name' => 'guide.md',
        ]);

        $this->assertCount(2, $units);
        $this->assertSame('Intro', $units[0]['metadata']['section']);
        $this->assertStringContainsString('Install it.', $units[1]['text']);
    }

    public function test_csv_parser_converts_rows_to_labeled_text(): void
    {
        $path = $this->writeTempFile("name,email\nAda,ada@example.com\n");

        $units = (new CsvParser)->parse($path, [
            'original_name' => 'customers.csv',
        ]);

        $this->assertCount(1, $units);
        $this->assertStringContainsString('name: Ada', $units[0]['text']);
        $this->assertSame('2-2', $units[0]['metadata']['row_range']);
    }

    public function test_excel_parser_reads_xlsx_sheets_and_rows(): void
    {
        $path = $this->writeSpreadsheet('xlsx');

        $units = (new ExcelParser)->parse($path, [
            'extension' => 'xlsx',
            'original_name' => 'customers.xlsx',
        ]);

        $this->assertCount(2, $units);
        $this->assertStringContainsString('name: Ada', $units[0]['text']);
        $this->assertSame('Customers', $units[0]['metadata']['sheet_name']);
        $this->assertSame('2-2', $units[0]['metadata']['row_range']);
        $this->assertStringContainsString('plan: Pro', $units[1]['text']);
        $this->assertSame('Plans', $units[1]['metadata']['sheet_name']);
    }

    public function test_excel_parser_reads_xls_workbooks(): void
    {
        $path = $this->writeSpreadsheet('xls');

        $units = (new ExcelParser)->parse($path, [
            'extension' => 'xls',
            'original_name' => 'customers.xls',
        ]);

        $this->assertNotEmpty($units);
        $this->assertSame('xls', $units[0]['metadata']['file_type']);
        $this->assertStringContainsString('name: Ada', $units[0]['text']);
    }

    public function test_docx_parser_extracts_paragraphs_and_tables(): void
    {
        $path = $this->writeDocx();

        $units = (new DocxParser)->parse($path, [
            'original_name' => 'handbook.docx',
        ]);

        $this->assertCount(1, $units);
        $this->assertSame('docx', $units[0]['metadata']['file_type']);
        $this->assertStringContainsString('Widget handbook', $units[0]['text']);
        $this->assertStringContainsString('Table row 1: Plan | Features', $units[0]['text']);
        $this->assertStringContainsString('Table row 2: Pro | Search and chat', $units[0]['text']);
    }

    public function test_html_parser_removes_non_content_blocks(): void
    {
        $path = $this->writeTempFile('<html><head><title>Docs</title><script>alert(1)</script></head><body><nav>Menu</nav><main>Hello docs.</main></body></html>');

        $units = (new HtmlParser)->parse($path, [
            'original_name' => 'page.html',
        ]);

        $this->assertCount(1, $units);
        $this->assertSame('Docs', $units[0]['metadata']['title']);
        $this->assertStringContainsString('Hello docs.', $units[0]['text']);
        $this->assertStringNotContainsString('alert', $units[0]['text']);
    }

    public function test_json_parser_flattens_paths(): void
    {
        $path = $this->writeTempFile('{"customer":{"name":"Ada"},"items":[{"sku":"A12"}]}');

        $units = (new JsonParser)->parse($path, [
            'original_name' => 'payload.json',
        ]);

        $this->assertCount(1, $units);
        $this->assertStringContainsString('customer.name: Ada', $units[0]['text']);
        $this->assertStringContainsString('items[0].sku: A12', $units[0]['text']);
    }

    public function test_xml_parser_flattens_nodes_and_attributes(): void
    {
        $path = $this->writeTempFile('<order id="7"><customer>Ada</customer></order>');

        $units = (new XmlParser)->parse($path, [
            'original_name' => 'order.xml',
        ]);

        $this->assertCount(1, $units);
        $this->assertStringContainsString('order.@id: 7', $units[0]['text']);
        $this->assertStringContainsString('order.customer: Ada', $units[0]['text']);
    }

    private function writeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rag-parser-');
        $this->assertIsString($path);
        file_put_contents($path, $contents);

        return $path;
    }

    private function writeSpreadsheet(string $extension): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Customers');
        $spreadsheet->getActiveSheet()->fromArray([
            ['name', 'email'],
            ['Ada', 'ada@example.com'],
        ]);

        $plans = $spreadsheet->createSheet();
        $plans->setTitle('Plans');
        $plans->fromArray([
            ['plan', 'price'],
            ['Pro', '29'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'rag-parser-').'.'.$extension;
        $writer = $extension === 'xls' ? new Xls($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function writeDocx(): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Widget handbook');
        $section->addText('Install the embeddable widget.');

        $table = $section->addTable();
        $table->addRow();
        $table->addCell()->addText('Plan');
        $table->addCell()->addText('Features');
        $table->addRow();
        $table->addCell()->addText('Pro');
        $table->addCell()->addText('Search and chat');

        $path = tempnam(sys_get_temp_dir(), 'rag-parser-').'.docx';
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }
}
