<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Services\Rag\ChatAnswerService;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectChatController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $accessService,
        private readonly ChatAnswerService $chatAnswerService,
    ) {}

    public function createSession(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:30'],
        ]);

        $session = ChatSession::query()->create([
            'tenant_id' => $resolvedProject->tenant_id,
            'project_id' => $resolvedProject->id,
            'user_id' => $request->user()->id,
            'title' => $payload['title'] ?? null,
            'channel' => $payload['channel'] ?? 'widget',
        ]);

        return response()->json(['data' => $session], 201);
    }

    public function index(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $sessions = ChatSession::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->latest('updated_at')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    public function sendMessage(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'chat_session_id' => ['required', 'integer', 'exists:chat_sessions,id'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $session = ChatSession::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->where('id', $payload['chat_session_id'])
            ->firstOrFail();

        $userMessage = $session->messages()->create([
            'tenant_id' => $resolvedProject->tenant_id,
            'project_id' => $resolvedProject->id,
            'user_id' => $request->user()->id,
            'role' => 'user',
            'content' => $payload['message'],
        ]);

        $result = $this->chatAnswerService->answer($resolvedProject, $session, $payload['message']);

        return response()->json([
            'data' => [
                'chat_session_id' => $session->id,
                'user_message' => $userMessage,
                'assistant_message' => $result['message'],
                'citations' => $result['citations'],
            ],
        ]);
    }

    public function history(Request $request, int $project, int $chat): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $session = ChatSession::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->where('id', $chat)
            ->firstOrFail();

        $messages = $session->messages()->with('citations')->orderBy('id')->get();

        return response()->json(['data' => $messages]);
    }
}


