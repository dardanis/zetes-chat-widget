<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;

class WebsiteCrawlerService
{
    /**
     * @return array<int, array{url:string,title:string,content:string}>
     */
    public function crawlSite(string $startUrl, int $maxPages = 20): array
    {
        $normalizedStartUrl = $this->normalizeUrl($startUrl);

        if (! $normalizedStartUrl) {
            return [];
        }

        $allowedHost = (string) parse_url($normalizedStartUrl, PHP_URL_HOST);
        $pending = [$normalizedStartUrl];
        $queued = [$normalizedStartUrl => true];
        $visited = [];
        $pages = [];

        while ($pending !== [] && count($pages) < $maxPages) {
            $url = array_shift($pending);

            if (! is_string($url) || isset($visited[$url])) {
                continue;
            }

            $visited[$url] = true;

            $response = Http::accept('text/html,application/xhtml+xml')
                ->timeout((int) config('rag.crawler.request_timeout_seconds', 10))
                ->get($url);

            if (! $response->ok()) {
                continue;
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));

            if (! str_contains($contentType, 'text/html')) {
                continue;
            }

            $parsed = $this->parseHtml($response->body(), $url, $allowedHost);

            if ($parsed['content'] !== '') {
                $pages[] = [
                    'url' => $url,
                    'title' => $parsed['title'],
                    'content' => mb_substr($parsed['content'], 0, (int) config('rag.crawler.max_content_chars', 120000)),
                ];
            }

            foreach ($parsed['links'] as $link) {
                if (isset($visited[$link]) || isset($queued[$link])) {
                    continue;
                }

                if (count($visited) + count($pending) >= $maxPages * 4) {
                    break;
                }

                $queued[$link] = true;
                $pending[] = $link;
            }
        }

        return $pages;
    }

    private function normalizeUrl(string $url): ?string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        $parts = parse_url($trimmed);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        $query = (string) ($parts['query'] ?? '');

        if ($path === '') {
            $path = '/';
        }

        $normalized = $scheme.'://'.$host;

        if (isset($parts['port']) && is_int($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $normalized .= $path;

        if ($query !== '') {
            $normalized .= '?'.$query;
        }

        return $normalized;
    }

    /**
     * @return array{title:string,content:string,links:array<int,string>}
     */
    private function parseHtml(string $html, string $baseUrl, string $allowedHost): array
    {
        $title = '';
        $content = '';
        $links = [];

        if ($html === '') {
            return compact('title', 'content', 'links');
        }

        $dom = new \DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $titleNode = $dom->getElementsByTagName('title')->item(0);
        $title = trim($titleNode?->textContent ?? '');

        foreach (['script', 'style', 'noscript'] as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);

            for ($index = $nodes->length - 1; $index >= 0; $index--) {
                $node = $nodes->item($index);
                $node?->parentNode?->removeChild($node);
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $content = trim(preg_replace('/\s+/u', ' ', (string) ($body?->textContent ?? '')) ?? '');

        $anchorNodes = $dom->getElementsByTagName('a');

        for ($index = 0; $index < $anchorNodes->length; $index++) {
            $href = trim((string) $anchorNodes->item($index)?->getAttribute('href'));

            $resolved = $this->resolveUrl($baseUrl, $href);

            if (! $resolved) {
                continue;
            }

            $host = (string) parse_url($resolved, PHP_URL_HOST);

            if ($host !== $allowedHost) {
                continue;
            }

            $links[$resolved] = $resolved;
        }

        return [
            'title' => $title,
            'content' => $content,
            'links' => array_values($links),
        ];
    }

    private function resolveUrl(string $baseUrl, string $href): ?string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        $lower = strtolower($href);

        if (str_starts_with($lower, 'mailto:') || str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'tel:')) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $baseScheme = (string) parse_url($baseUrl, PHP_URL_SCHEME);

            return $this->normalizeUrl($baseScheme.':'.$href);
        }

        if (parse_url($href, PHP_URL_SCHEME)) {
            return $this->normalizeUrl($href);
        }

        $baseParts = parse_url($baseUrl);

        if (! is_array($baseParts)) {
            return null;
        }

        $scheme = (string) ($baseParts['scheme'] ?? 'http');
        $host = (string) ($baseParts['host'] ?? '');
        $port = isset($baseParts['port']) && is_int($baseParts['port']) ? ':'.$baseParts['port'] : '';

        if ($host === '') {
            return null;
        }

        if (str_starts_with($href, '/')) {
            return $this->normalizeUrl($scheme.'://'.$host.$port.$href);
        }

        $basePath = (string) ($baseParts['path'] ?? '/');
        $directory = preg_replace('/[^\/]+$/', '', $basePath) ?? '/';

        return $this->normalizeUrl($scheme.'://'.$host.$port.$directory.$href);
    }
}

