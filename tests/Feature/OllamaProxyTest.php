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

    public function test_it_auto_routes_json_chat_payload_to_chat_endpoint_when_path_is_missing(): void
    {
        config([
            'rag.ollama.base_url' => 'http://ollama.test',
            'rag.ollama.generation_model' => 'llama3',
        ]);

        Http::fake([
            'http://ollama.test/api/chat' => Http::response([
                'message' => ['content' => 'Hello from Ollama'],
            ]),
        ]);

        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'stream' => false,
        ];

        $response = $this->postJson('/api/ollama', $payload);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'Hello from Ollama');

        Http::assertSent(static fn ($request): bool => $request->method() === 'POST'
            && (string) $request->url() === 'http://ollama.test/api/chat'
            && $request['model'] === 'llama3');
    }

    public function test_it_uses_model_from_query_parameter_when_calling_generate(): void
    {
        config([
            'rag.ollama.base_url' => 'http://ollama.test',
            'rag.ollama.generation_model' => 'llama3',
        ]);

        Http::fake([
            'http://ollama.test/api/generate?model=llama3.2' => Http::response([
                'response' => 'Hello from Ollama',
            ]),
        ]);

        $payload = [
            'prompt' => 'Hello',
            'stream' => false,
        ];

        $response = $this->postJson('/api/ollama/api/generate?model=llama3.2', $payload);

        $response->assertOk();
        $response->assertJsonPath('response', 'Hello from Ollama');

        Http::assertSent(static fn ($request): bool => $request->method() === 'POST'
            && (string) $request->url() === 'http://ollama.test/api/generate?model=llama3.2'
            && $request['model'] === 'llama3.2');
    }

    public function test_it_forwards_openai_compatible_chat_completions(): void
    {
        config([
            'rag.ollama.base_url' => 'http://ollama.test',
            'rag.ollama.generation_model' => 'llama3',
        ]);

        Http::fake([
            'http://ollama.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => '9.8 is larger.']],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/ollama/v1/chat/completions', [
            'model' => 'gemma:2b',
            'messages' => [
                ['role' => 'user', 'content' => 'Which number is larger, 9.11 or 9.8?'],
            ],
            'temperature' => 1,
            'top_p' => 0.95,
            'max_tokens' => 8192,
            'stream' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('choices.0.message.content', '9.8 is larger.');

        Http::assertSent(static fn ($request): bool => (string) $request->url() === 'http://ollama.test/v1/chat/completions'
            && json_decode($request->body(), true) === [
                'model' => 'gemma:2b',
                'messages' => [
                    ['role' => 'user', 'content' => 'Which number is larger, 9.11 or 9.8?'],
                ],
                'temperature' => 1,
                'top_p' => 0.95,
                'max_tokens' => 8192,
                'stream' => false,
            ]);
    }

    public function test_it_forwards_empty_json_objects_without_reshaping_them(): void
    {
        config(['rag.ollama.base_url' => 'http://ollama.test']);

        Http::fake([
            'http://ollama.test/v1/chat/completions' => Http::response(['choices' => []]),
        ]);

        // Re-encoding a decoded payload would turn `{}` into `[]`, which upstream rejects
        // with "cannot unmarshal array into Go struct field".
        $body = '{"model":"gemma:2b","messages":[],"stream":false,"stream_options":{}}';

        $this->call(
            'POST',
            '/api/ollama/v1/chat/completions',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $body,
        )->assertOk();

        Http::assertSent(static fn ($request): bool => str_contains($request->body(), '"stream_options":{}'));
    }

    public function test_it_defaults_the_model_on_openai_compatible_requests(): void
    {
        config([
            'rag.ollama.base_url' => 'http://ollama.test',
            'rag.ollama.generation_model' => 'llama3',
        ]);

        Http::fake([
            'http://ollama.test/v1/chat/completions' => Http::response(['choices' => []]),
        ]);

        $this->postJson('/api/ollama/v1/chat/completions', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'stream' => false,
        ])->assertOk();

        Http::assertSent(static fn ($request): bool => $request['model'] === 'llama3');
    }

    public function test_it_accepts_a_bearer_api_key_from_a_remote_client(): void
    {
        config([
            'rag.ollama.base_url' => 'http://ollama.test',
            'rag.ollama.proxy_api_key' => 'secret-key',
        ]);

        Http::fake([
            'http://ollama.test/v1/models' => Http::response(['data' => []]),
        ]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeader('Authorization', 'Bearer secret-key')
            ->getJson('/api/ollama/v1/models')
            ->assertOk();

        // The caller key authenticates against the proxy and must not leak upstream.
        Http::assertSent(static fn ($request): bool => $request->header('Authorization') === []);
    }

    public function test_it_rejects_a_wrong_api_key_with_an_openai_shaped_error(): void
    {
        config([
            'rag.ollama.base_url' => 'http://ollama.test',
            'rag.ollama.proxy_api_key' => 'secret-key',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer wrong-key')
            ->getJson('/api/ollama/v1/models');

        $response->assertUnauthorized();
        $response->assertJsonPath('error.type', 'invalid_api_key');

        Http::assertNothingSent();
    }

    public function test_it_blocks_remote_requests_by_default(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->getJson('/api/ollama/api/tags');

        $response->assertForbidden();
        $response->assertJsonPath('error.type', 'forbidden');

        Http::assertNothingSent();
    }
}
