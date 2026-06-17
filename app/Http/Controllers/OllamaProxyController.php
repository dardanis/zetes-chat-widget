<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OllamaProxyController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): Response
    {
//        if (! config('rag.ollama.proxy_allow_remote') && ! $this->isLocalAddress($request->ip())) {
//            return response([
//                'message' => 'Ollama proxy is restricted to local requests.',
//            ], SymfonyResponse::HTTP_FORBIDDEN);
//        }

        $url = $this->buildUpstreamUrl($path, $request->getQueryString());

        try {
            $upstreamResponse = Http::timeout(config('rag.ollama.timeout'))
                ->withHeaders($this->forwardHeaders($request))
                ->send($request->method(), $url, $this->requestOptions($request));
        } catch (ConnectionException) {
            return response([
                'message' => 'Unable to connect to Ollama.',
            ], SymfonyResponse::HTTP_BAD_GATEWAY);
        }

        $response = response($upstreamResponse->body(), $upstreamResponse->status());

        if ($contentType = $upstreamResponse->header('Content-Type')) {
            $response->header('Content-Type', $contentType);
        }

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
            'connection',
            'content-length',
            'cookie',
            'host',
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
     * @return array<string, string>
     */
    private function requestOptions(Request $request): array
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return [];
        }

        return ['body' => $request->getContent()];
    }

    private function isLocalAddress(?string $address): bool
    {
        return in_array($address, ['127.0.0.1', '::1'], true);
    }
}
