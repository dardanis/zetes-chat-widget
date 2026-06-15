<?php

namespace App\Services\Rag\Parsers;

interface DocumentParserInterface
{
    public function supports(string $extension, string $mimeType): bool;

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array{text:string, metadata:array<string, mixed>}>
     */
    public function parse(string $filePath, array $context = []): array;
}
