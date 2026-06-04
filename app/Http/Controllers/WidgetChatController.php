<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Project;
use App\Services\Rag\ChatAnswerService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WidgetChatController extends Controller
{
    public function __construct(private readonly ChatAnswerService $chatAnswerService) {}

    public function createSession(Request $request, string $widgetKey): JsonResponse
    {
        $project = Project::query()->where('widget_key', $widgetKey)->firstOrFail();
        $this->assertWidgetSecretIsValid($request, $project);

        $payload = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'user_token' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'user_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $sessionToken = Str::random(64);
        $requestTimestamp = now();
        $widgetIdentity = $this->resolveWidgetIdentity($request, $payload);

        $session = ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => $payload['title'] ?? 'Widget session',
            'channel' => 'widget',
            'metadata' => $this->mergeSessionMetadata([
                'session_token_hash' => Hash::make($sessionToken),
                'session_token_issued_at' => $requestTimestamp->toIso8601String(),
            ], $widgetIdentity, $requestTimestamp),
        ]);

        return response()->json([
            'data' => $session,
            'session_token' => $sessionToken,
        ], 201);
    }

    public function sendMessage(Request $request, string $widgetKey): JsonResponse
    {
        $project = Project::query()->where('widget_key', $widgetKey)->firstOrFail();
        $this->assertWidgetSecretIsValid($request, $project);

        $payload = $request->validate([
            'chat_session_id' => ['required', 'integer', 'exists:chat_sessions,id'],
            'message' => ['required', 'string', 'max:5000'],
            'session_token' => ['required', 'string', 'min:32'],
            'user_token' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'user_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $session = ChatSession::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->where('id', $payload['chat_session_id'])
            ->firstOrFail();

        $metadata = is_array($session->metadata) ? $session->metadata : [];
        $tokenHash = (string) ($metadata['session_token_hash'] ?? '');
        $issuedAt = isset($metadata['session_token_issued_at']) ? Carbon::parse($metadata['session_token_issued_at']) : null;
        $maxAge = (int) config('rag.widget.session_ttl_seconds');
        $requestTimestamp = now();
        $widgetIdentity = $this->resolveWidgetIdentity($request, $payload);

        abort_unless($tokenHash !== '' && Hash::check($payload['session_token'], $tokenHash), 403, 'Invalid widget session token.');
        abort_unless($issuedAt && $issuedAt->diffInSeconds(now()) <= $maxAge, 403, 'Widget session token has expired.');

        $session->forceFill([
            'metadata' => $this->mergeSessionMetadata($metadata, $widgetIdentity, $requestTimestamp),
        ])->save();

        $userMessage = $session->messages()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'role' => 'user',
            'content' => $payload['message'],
            'metadata' => array_filter([
                'channel' => 'widget',
                'widget_user_email' => $widgetIdentity['email'],
                'widget_request_at' => $requestTimestamp->toIso8601String(),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);

        $result = $this->chatAnswerService->answer($project, $session, $payload['message']);

        return response()->json([
            'data' => [
                'chat_session_id' => $session->id,
                'user_message' => $userMessage,
                'assistant_message' => $result['message'],
                'citations' => $result['citations'],
            ],
        ]);
    }

    private function assertWidgetSecretIsValid(Request $request, Project $project): void
    {
        $secret = (string) $request->header('X-Widget-Secret', '');

        abort_unless($project->widget_secret_hash && $secret !== '' && Hash::check($secret, $project->widget_secret_hash), 403, 'Invalid widget secret.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token_present: bool, token_source: string|null, email: string|null, name: string|null, subject: string|null}
     */
    private function resolveWidgetIdentity(Request $request, array $payload): array
    {
        [$token, $tokenSource] = $this->extractWidgetUserToken($request, $payload);
        $claims = $this->decodeJwtPayload($token);
        $explicitEmail = $this->normalizeEmail(
            $payload['user_email'] ?? $request->header('X-Widget-User-Email')
        );

        return [
            'token_present' => $token !== null,
            'token_source' => $tokenSource,
            'email' => $this->normalizeEmail($this->extractClaimValue($claims, ['email', 'preferred_username', 'upn', 'unique_name'])) ?? $explicitEmail,
            'name' => $this->extractClaimValue($claims, ['name', 'given_name']),
            'subject' => $this->extractClaimValue($claims, ['sub']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string|null, 1: string|null}
     */
    private function extractWidgetUserToken(Request $request, array $payload): array
    {
        $authorization = trim((string) $request->header('Authorization', ''));

        if ($authorization !== '' && str_starts_with(strtolower($authorization), 'bearer ')) {
            return [trim(substr($authorization, 7)), 'authorization'];
        }

        $headerToken = trim((string) $request->header('X-Widget-User-Token', ''));

        if ($headerToken !== '') {
            return [$headerToken, 'header'];
        }

        $payloadToken = trim((string) ($payload['user_token'] ?? ''));

        if ($payloadToken !== '') {
            return [$payloadToken, 'payload'];
        }

        return [null, null];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(?string $token): array
    {
        if ($token === null || substr_count($token, '.') < 2) {
            return [];
        }

        $segments = explode('.', $token);
        $payload = $segments[1] ?? null;

        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $payload = strtr($payload, '-_', '+/');
        $padding = strlen($payload) % 4;

        if ($padding > 0) {
            $payload .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($payload, true);

        if (! is_string($decoded) || $decoded === '') {
            return [];
        }

        $claims = json_decode($decoded, true);

        return is_array($claims) ? $claims : [];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  list<string>  $keys
     */
    private function extractClaimValue(array $claims, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $claims[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        return trim($item);
                    }
                }
            }
        }

        return null;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));

        return $email !== '' ? $email : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{token_present: bool, token_source: string|null, email: string|null, name: string|null, subject: string|null}  $widgetIdentity
     * @return array<string, mixed>
     */
    private function mergeSessionMetadata(array $metadata, array $widgetIdentity, CarbonInterface $requestTimestamp): array
    {
        $existingIdentity = is_array($metadata['widget_user'] ?? null) ? $metadata['widget_user'] : [];

        $metadata['widget_session_created_at'] = $metadata['widget_session_created_at'] ?? $requestTimestamp->toIso8601String();
        $metadata['widget_last_request_at'] = $requestTimestamp->toIso8601String();
        $metadata['widget_user'] = [
            'token_present' => $widgetIdentity['token_present'] || (bool) ($existingIdentity['token_present'] ?? false),
            'token_source' => $widgetIdentity['token_source'] ?? $existingIdentity['token_source'] ?? null,
            'email' => $widgetIdentity['email'] ?? $existingIdentity['email'] ?? null,
            'name' => $widgetIdentity['name'] ?? $existingIdentity['name'] ?? null,
            'subject' => $widgetIdentity['subject'] ?? $existingIdentity['subject'] ?? null,
        ];

        return $metadata;
    }
}



