<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaProxyTest extends TestCase
{
    public function test_it_forwards_get_requests_to_ollama(): void
    {
        config(['rag.ollama.base_url' => 'http://ollama.test']);

        Http::fake([
            'http://ollama.test/api/tags?verbose=true' => Http::response([
                'models' => [
                    ['name' => 'llama3:latest'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/ollama/api/tags?verbose=true');

        $response->assertOk();
        $response->assertJsonPath('models.0.name', 'llama3:latest');

        Http::assertSent(static fn ($request): bool => $request->method() === 'GET'
            && (string) $request->url() === 'http://ollama.test/api/tags?verbose=true');
    }

    public function test_it_forwards_post_requests_to_ollama(): void
    {
        config(['rag.ollama.base_url' => 'http://ollama.test']);

        Http::fake([
            'http://ollama.test/api/chat' => Http::response([
                'message' => ['content' => 'Hello from Ollama'],
            ]),
        ]);

        $payload = [
            'model' => 'llama3',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'stream' => false,
        ];

        $response = $this->postJson('/api/ollama/api/chat', $payload);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'Hello from Ollama');

        Http::assertSent(static fn ($request): bool => $request->method() === 'POST'
            && (string) $request->url() === 'http://ollama.test/api/chat'
            && json_decode($request->body(), true) === $payload);
    }

    public function test_it_blocks_remote_requests_by_default(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->getJson('/api/ollama/api/tags');

        $response->assertForbidden();

        Http::assertNothingSent();
    }
}
