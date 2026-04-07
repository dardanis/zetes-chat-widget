<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Project;
use App\Services\Rag\ChatAnswerService;
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
        ]);

        $sessionToken = Str::random(64);

        $session = ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => $payload['title'] ?? 'Widget session',
            'channel' => 'widget',
            'metadata' => [
                'session_token_hash' => Hash::make($sessionToken),
                'session_token_issued_at' => now()->toIso8601String(),
            ],
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

        abort_unless($tokenHash !== '' && Hash::check($payload['session_token'], $tokenHash), 403, 'Invalid widget session token.');
        abort_unless($issuedAt && $issuedAt->diffInSeconds(now()) <= $maxAge, 403, 'Widget session token has expired.');

        $userMessage = $session->messages()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'role' => 'user',
            'content' => $payload['message'],
            'metadata' => ['channel' => 'widget'],
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
}



