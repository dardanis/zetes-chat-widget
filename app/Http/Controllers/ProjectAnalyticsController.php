<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatMessageFeedback;
use App\Models\ChatSession;
use App\Models\MessageCitation;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAnalyticsController extends Controller
{
    public function __construct(private readonly ProjectAccessService $accessService) {}

    public function __invoke(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $sessions = ChatSession::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->get(['id', 'created_at']);

        $messages = ChatMessage::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->orderBy('chat_session_id')
            ->orderBy('id')
            ->get(['id', 'chat_session_id', 'role', 'content', 'created_at']);

        $feedback = ChatMessageFeedback::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->get(['rating']);

        $helpful = $feedback->where('rating', 'helpful')->count();
        $unhelpful = $feedback->where('rating', 'unhelpful')->count();
        $feedbackTotal = $helpful + $unhelpful;

        return response()->json([
            'data' => [
                'total_chats' => $sessions->count(),
                'most_asked_questions' => $this->mostAskedQuestions($messages),
                'failed_no_answer_questions' => $this->failedQuestions($messages),
                'average_response_time_seconds' => $this->averageResponseTime($messages),
                'top_referenced_documents' => $this->topReferencedDocuments($resolvedProject->tenant_id, $resolvedProject->id),
                'feedback_score' => [
                    'helpful' => $helpful,
                    'unhelpful' => $unhelpful,
                    'total' => $feedbackTotal,
                    'positive_rate' => $feedbackTotal > 0 ? round(($helpful / $feedbackTotal) * 100, 1) : null,
                ],
            ],
        ]);
    }

    private function mostAskedQuestions($messages): array
    {
        return $messages
            ->where('role', 'user')
            ->groupBy(fn (ChatMessage $message): string => mb_strtolower(trim($message->content)))
            ->map(fn ($group): array => [
                'question' => $group->first()->content,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();
    }

    private function failedQuestions($messages): array
    {
        $failedMarkers = ['insufficient', 'not enough context', 'could not find', 'cannot find', 'no relevant', 'missing'];
        $failed = [];
        $bySession = $messages->groupBy('chat_session_id');

        foreach ($bySession as $sessionMessages) {
            $previousUserMessage = null;

            foreach ($sessionMessages as $message) {
                if ($message->role === 'user') {
                    $previousUserMessage = $message;

                    continue;
                }

                if ($message->role !== 'assistant' || ! $previousUserMessage) {
                    continue;
                }

                $content = mb_strtolower($message->content);

                foreach ($failedMarkers as $marker) {
                    if (str_contains($content, $marker)) {
                        $failed[] = [
                            'question' => $previousUserMessage->content,
                            'answer' => $message->content,
                            'created_at' => $message->created_at,
                        ];
                        break;
                    }
                }
            }
        }

        return array_slice(array_reverse($failed), 0, 10);
    }

    private function averageResponseTime($messages): ?float
    {
        $durations = [];
        $bySession = $messages->groupBy('chat_session_id');

        foreach ($bySession as $sessionMessages) {
            $lastUserMessage = null;

            foreach ($sessionMessages as $message) {
                if ($message->role === 'user') {
                    $lastUserMessage = $message;

                    continue;
                }

                if ($message->role === 'assistant' && $lastUserMessage) {
                    $durations[] = $lastUserMessage->created_at->diffInSeconds($message->created_at);
                    $lastUserMessage = null;
                }
            }
        }

        return $durations === [] ? null : round(array_sum($durations) / count($durations), 2);
    }

    private function topReferencedDocuments(int $tenantId, int $projectId): array
    {
        return MessageCitation::query()
            ->whereHas('message', function ($query) use ($tenantId, $projectId): void {
                $query->where('tenant_id', $tenantId)->where('project_id', $projectId);
            })
            ->get()
            ->groupBy(fn (MessageCitation $citation): string => (string) data_get($citation->metadata, 'document_name', 'Document'))
            ->map(fn ($group, string $documentName): array => [
                'document_name' => $documentName,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();
    }
}
