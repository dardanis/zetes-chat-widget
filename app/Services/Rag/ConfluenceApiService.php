<?php

namespace App\Services\Rag;

use App\Models\AtlassianConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConfluenceApiService
{
    /**
     * @return array<int, array{id:string,key:string,name:string,type:string}>
     */
    public function listSpaces(AtlassianConnection $connection): array
    {
        $spaces = [];
        $url = $this->buildPathUrl($connection->base_url, '/wiki/rest/api/space?limit=100&type=global');
        $maxSpaces = max((int) config('rag.confluence.max_spaces_per_request', 500), 1);

        while ($url !== null && count($spaces) < $maxSpaces) {
            $response = $this->request($connection)->get($url);

            if (! $response->ok()) {
                throw new RuntimeException('Unable to fetch Confluence spaces.');
            }

            $payload = $response->json();

            foreach ((array) data_get($payload, 'results', []) as $item) {
                $spaces[] = [
                    'id' => (string) data_get($item, 'id', data_get($item, 'key', '')),
                    'key' => (string) data_get($item, 'key', ''),
                    'name' => (string) data_get($item, 'name', ''),
                    'type' => (string) data_get($item, 'type', 'global'),
                ];

                if (count($spaces) >= $maxSpaces) {
                    break;
                }
            }

            $url = $this->resolveNextUrl($connection->base_url, (string) data_get($payload, '_links.next', ''));
        }

        return array_values(array_filter($spaces, static fn (array $space): bool => $space['key'] !== ''));
    }

    /**
     * @return array<int, array{id:string,title:string,url:string,updated_at:string|null}>
     */
    public function listPagesForSpace(AtlassianConnection $connection, string $spaceKey): array
    {
        $encodedKey = rawurlencode($spaceKey);
        $url = $this->buildPathUrl(
            $connection->base_url,
            '/wiki/rest/api/content?spaceKey='.$encodedKey.'&type=page&limit=50&expand=version',
        );
        $pages = [];
        $maxPages = max((int) config('rag.confluence.max_pages_per_sync', 200), 1);

        while ($url !== null && count($pages) < $maxPages) {
            $response = $this->request($connection)->get($url);

            if (! $response->ok()) {
                throw new RuntimeException('Unable to fetch Confluence pages.');
            }

            $payload = $response->json();

            foreach ((array) data_get($payload, 'results', []) as $item) {
                $pages[] = [
                    'id' => (string) data_get($item, 'id', ''),
                    'title' => (string) data_get($item, 'title', ''),
                    'url' => $this->resolveContentUrl($connection->base_url, (string) data_get($item, '_links.webui', '')),
                    'updated_at' => data_get($item, 'version.when') ? (string) data_get($item, 'version.when') : null,
                ];

                if (count($pages) >= $maxPages) {
                    break;
                }
            }

            $url = $this->resolveNextUrl($connection->base_url, (string) data_get($payload, '_links.next', ''));
        }

        return array_values(array_filter($pages, static fn (array $page): bool => $page['id'] !== ''));
    }

    /**
     * @return array{id:string,title:string,url:string,body_html:string,updated_at:string|null}|null
     */
    public function getPageBody(AtlassianConnection $connection, string $pageId): ?array
    {
        $url = $this->buildPathUrl(
            $connection->base_url,
            '/wiki/rest/api/content/'.rawurlencode($pageId).'?expand=body.storage,version',
        );
        $response = $this->request($connection)->get($url);

        if (! $response->ok()) {
            return null;
        }

        $payload = $response->json();

        return [
            'id' => (string) data_get($payload, 'id', ''),
            'title' => (string) data_get($payload, 'title', ''),
            'url' => $this->resolveContentUrl($connection->base_url, (string) data_get($payload, '_links.webui', '')),
            'body_html' => (string) data_get($payload, 'body.storage.value', ''),
            'updated_at' => data_get($payload, 'version.when') ? (string) data_get($payload, 'version.when') : null,
        ];
    }

    public function toPlainText(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', strip_tags($decoded)) ?? '');
    }

    private function request(AtlassianConnection $connection): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('rag.confluence.request_timeout_seconds', 20))
            ->withBasicAuth($connection->email, $connection->api_token);
    }

    private function resolveContentUrl(string $baseUrl, string $webUiPath): string
    {
        if ($webUiPath === '') {
            return rtrim($baseUrl, '/');
        }

        if (str_starts_with($webUiPath, 'http://') || str_starts_with($webUiPath, 'https://')) {
            return $webUiPath;
        }

        return rtrim($baseUrl, '/').'/wiki'.(str_starts_with($webUiPath, '/') ? $webUiPath : '/'.$webUiPath);
    }

    private function resolveNextUrl(string $baseUrl, string $next): ?string
    {
        if ($next === '') {
            return null;
        }

        if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://')) {
            return $next;
        }

        return $this->buildPathUrl($baseUrl, $next);
    }

    private function buildPathUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
