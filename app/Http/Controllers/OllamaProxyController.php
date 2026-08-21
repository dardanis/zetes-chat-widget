<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OllamaProxyController extends Controller
{
    /**
     * Endpoints that carry a `model` field, so an omitted model can fall back to config.
     * Covers both the native Ollama API and its OpenAI-compatible `/v1` surface.
     */
    private const MODEL_AWARE_PATHS = [
        'api/chat',
        'api/generate',
        'api/embed',
        'api/embeddings',
        'v1/chat/completions',
        'v1/completions',
        'v1/embeddings',
    ];

    private const EMBEDDING_PATHS = [
        'api/embed',
        'api/embeddings',
        'v1/embeddings',
    ];

    /**
     * The native Ollama endpoints stream unless a payload says otherwise; the
     * OpenAI-compatible ones default to one buffered response, like api.openai.com.
     */
    private const STREAM_BY_DEFAULT_PATHS = [
        'api/chat',
        'api/generate',
    ];

    public function __invoke(Request $request, ?string $path = null): SymfonyResponse
    {
        if ($rejection = $this->rejectUnauthorized($request)) {
            return $rejection;
        }

        $payload = $this->jsonPayload($request);
        $resolvedPath = $this->resolveUpstreamPath($request, $path, $payload);
        $url = $this->buildUpstreamUrl($resolvedPath, $request->getQueryString());
        $streaming = $this->wantsStream($request, $resolvedPath, $payload);

        try {
            $upstreamResponse = Http::timeout(config('rag.ollama.timeout'))
                ->withOptions($streaming ? ['stream' => true] : [])
                ->withHeaders($this->forwardHeaders($request))
                ->send($request->method(), $url, $this->requestOptions($request, $resolvedPath, $payload));
        } catch (ConnectionException) {
            return $this->errorResponse(
                'Unable to connect to Ollama.',
                'upstream_unavailable',
                SymfonyResponse::HTTP_BAD_GATEWAY,
            );
        }

        // An upstream failure is a short JSON body, never a stream, so buffer it: the
        // error then reaches the client intact and can be logged.
        if ($streaming && $upstreamResponse->status() < SymfonyResponse::HTTP_BAD_REQUEST) {
            $this->debugLog($request, $url, $upstreamResponse, null);

            return $this->streamedResponse($upstreamResponse);
        }

        $this->debugLog($request, $url, $upstreamResponse, $upstreamResponse->body());

        return $this->bufferedResponse($upstreamResponse);
    }

    /**
     * Enabled with OLLAMA_PROXY_DEBUG=true. Records exactly what a client sent and what
     * Ollama answered, which is the only way to see inside an opaque IDE integration.
     */
    private function debugLog(Request $request, string $url, ClientResponse $upstreamResponse, ?string $body): void
    {
        if (! config('rag.ollama.proxy_debug')) {
            return;
        }

        Log::debug('[ollama-proxy]', [
            'method' => $request->method(),
            'from' => $request->ip(),
            'incoming_path' => $request->path(),
            'upstream_url' => $url,
            'user_agent' => $request->userAgent(),
            'request_body' => $request->getContent(),
            'status' => $upstreamResponse->status(),
            'response_body' => $body === null ? '(streamed)' : mb_substr($body, 0, 4000),
        ]);
    }

    /**
     * When an API key is configured it becomes the only gate, so a client on another
     * host can authenticate the way an OpenAI SDK does. Without one the proxy stays
     * local-only unless remote access is explicitly enabled.
     */
    private function rejectUnauthorized(Request $request): ?SymfonyResponse
    {
        $configuredKey = trim((string) config('rag.ollama.proxy_api_key'));

        if ($configuredKey !== '') {
            return hash_equals($configuredKey, $this->presentedApiKey($request))
                ? null
                : $this->errorResponse(
                    'Incorrect API key provided.',
                    'invalid_api_key',
                    SymfonyResponse::HTTP_UNAUTHORIZED,
                );
        }

        if (! config('rag.ollama.proxy_allow_remote') && ! $this->isLocalAddress($request->ip())) {
            return $this->errorResponse(
                'Ollama proxy is restricted to local requests.',
                'forbidden',
                SymfonyResponse::HTTP_FORBIDDEN,
            );
        }

        return null;
    }

    private function presentedApiKey(Request $request): string
    {
        $bearerToken = $request->bearerToken();

        if (is_string($bearerToken) && trim($bearerToken) !== '') {
            return trim($bearerToken);
        }

        return trim((string) $request->header('X-Api-Key', ''));
    }

    private function bufferedResponse(ClientResponse $upstreamResponse): SymfonyResponse
    {
        $response = response($upstreamResponse->body(), $upstreamResponse->status());

        if ($contentType = $upstreamResponse->header('Content-Type')) {
            $response->header('Content-Type', $contentType);
        }

        return $response;
    }

    /**
     * Relays upstream chunks as they arrive, so a client using `stream=True` sees
     * tokens instead of one buffered dump at the end of the generation.
     */
    private function streamedResponse(ClientResponse $upstreamResponse): StreamedResponse
    {
        $body = $upstreamResponse->toPsrResponse()->getBody();

        $response = new StreamedResponse(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(8192);

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }

            $body->close();
        }, $upstreamResponse->status());

        if ($contentType = $upstreamResponse->header('Content-Type')) {
            $response->headers->set('Content-Type', $contentType);
        }

        // Reverse proxies (nginx, IIS) buffer proxied bodies by default, which would
        // undo the streaming above before it ever reached the client.
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function buildUpstreamUrl(?string $path, ?string $queryString): string
    {
        $url = rtrim((string) config('rag.ollama.base_url'), '/').'/'.ltrim((string) $path, '/');

        if ($queryString !== null && $queryString !== '') {
            $url .= '?'.$queryString;
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    private function forwardHeaders(Request $request): array
    {
        $blockedHeaders = [
            // Consumed by this proxy: the caller key authenticates against us, not Ollama.
            'authorization',
            'connection',
            'content-length',
            'cookie',
            'host',
            'x-api-key',
            'x-csrf-token',
            'x-xsrf-token',
        ];

        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $blockedHeaders, true)) {
                continue;
            }

            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonPayload(Request $request): ?array
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true) || ! $request->isJson()) {
            return null;
        }

        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function requestOptions(Request $request, string $path, ?array $payload): array
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return [];
        }

        $content = $request->getContent();

        if ($payload === null) {
            return ['body' => $content];
        }

        $model = $this->resolveRequestedModel($request, $payload, $path);

        // Re-encoding turns every empty JSON object into an empty array, which upstream
        // then rejects. Clients that already name a model (editors always do) get their
        // bytes forwarded exactly as sent.
        if ($model === null || $model === ($payload['model'] ?? null)) {
            return ['body' => $content];
        }

        $payload['model'] = $model;

        return ['json' => $payload];
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function resolveUpstreamPath(Request $request, ?string $path, ?array $payload): string
    {
        if ($path !== null && trim($path) !== '') {
            return ltrim($path, '/');
        }

        if ($payload === null) {
            return '';
        }

        if (isset($payload['messages'])) {
            return 'api/chat';
        }

        if (isset($payload['prompt'])) {
            return 'api/generate';
        }

        if (isset($payload['input'])) {
            return 'api/embeddings';
        }

        return '';
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function wantsStream(Request $request, string $path, ?array $payload): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($payload !== null && array_key_exists('stream', $payload)) {
            return $payload['stream'] === true;
        }

        return in_array($path, self::STREAM_BY_DEFAULT_PATHS, true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRequestedModel(Request $request, array $payload, string $path): ?string
    {
        if (! $this->supportsModelSelection($path)) {
            return null;
        }

        $queryModel = $request->query('model');

        if (is_string($queryModel) && trim($queryModel) !== '') {
            return trim($queryModel);
        }

        $bodyModel = $payload['model'] ?? null;

        if (is_string($bodyModel) && trim($bodyModel) !== '') {
            return trim($bodyModel);
        }

        return in_array($path, self::EMBEDDING_PATHS, true)
            ? (string) config('rag.ollama.embedding_model')
            : (string) config('rag.ollama.generation_model');
    }

    private function supportsModelSelection(string $path): bool
    {
        return in_array($path, self::MODEL_AWARE_PATHS, true);
    }

    /**
     * OpenAI clients read `error.message`, so failures raised here match the shape
     * they already parse instead of a bare Laravel message.
     */
    private function errorResponse(string $message, string $type, int $status): SymfonyResponse
    {
        return response([
            'error' => [
                'message' => $message,
                'type' => $type,
            ],
        ], $status);
    }

    private function isLocalAddress(?string $address): bool
    {
        return in_array($address, ['127.0.0.1', '::1'], true);
    }
}
