<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatMessageFeedback;
use App\Models\ChatSession;
use App\Models\MessageCitation;
use App\Models\PhoneCall;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectAnalyticsController extends Controller
{
    public function __construct(private readonly ProjectAccessService $accessService) {}

    public function __invoke(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $request->validate([
            'channel' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $channel = $request->filled('channel') ? (string) $request->string('channel') : null;

        $sessionQuery = ChatSession::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->when($channel !== null, fn ($query) => $query->where('channel', $channel));

        // Counts are aggregated in SQL rather than by loading every row: transcripts (voice ones
        // especially) grow without bound, and this endpoint used to hydrate all of them.
        $totalChats = (clone $sessionQuery)->count();

        $chatsByChannel = ChatSession::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->groupBy('channel')
            ->pluck(DB::raw('COUNT(*)'), 'channel')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $messages = ChatMessage::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->when($channel !== null, fn ($query) => $query->whereIn(
                'chat_session_id',
                (clone $sessionQuery)->select('id')
            ))
            ->orderBy('chat_session_id')
            ->orderBy('id')
            ->get(['id', 'chat_session_id', 'role', 'content', 'created_at']);

        $feedbackCounts = ChatMessageFeedback::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->groupBy('rating')
            ->pluck(DB::raw('COUNT(*)'), 'rating');

        $helpful = (int) ($feedbackCounts['helpful'] ?? 0);
        $unhelpful = (int) ($feedbackCounts['unhelpful'] ?? 0);
        $feedbackTotal = $helpful + $unhelpful;

        return response()->json([
            'data' => [
                'total_chats' => $totalChats,
                'chats_by_channel' => $chatsByChannel,
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
                'voice' => $this->voiceMetrics($resolvedProject->tenant_id, $resolvedProject->id),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function voiceMetrics(int $tenantId, int $projectId): array
    {
        $base = fn () => PhoneCall::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId);

        $totals = $base()
            ->selectRaw('COUNT(*) as total_calls')
            ->selectRaw('COUNT(DISTINCT from_number) as unique_callers')
            ->selectRaw('AVG(duration_seconds) as avg_duration')
            ->selectRaw('SUM(COALESCE(duration_seconds, 0)) as total_seconds')
            ->selectRaw('AVG(turn_count) as avg_turns')
            ->first();

        $totalCalls = (int) ($totals->total_calls ?? 0);
        $completed = $base()->where('status', 'completed')->count();

        $topCallers = $base()
            ->groupBy('from_number')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->pluck(DB::raw('COUNT(*)'), 'from_number')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $callsByDay = $base()
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->pluck(DB::raw('COUNT(*)'), DB::raw('DATE(created_at)'))
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completed,
            'completion_rate' => $totalCalls > 0 ? round(($completed / $totalCalls) * 100, 1) : null,
            'average_call_duration_seconds' => $totals->avg_duration !== null ? round((float) $totals->avg_duration, 1) : null,
            'average_turns_per_call' => $totals->avg_turns !== null ? round((float) $totals->avg_turns, 1) : null,
            'total_minutes' => (int) round(((int) ($totals->total_seconds ?? 0)) / 60),
            'unique_callers' => (int) ($totals->unique_callers ?? 0),
            'top_calling_numbers' => collect($topCallers)
                ->map(static fn (int $count, string $number): array => ['from_number' => $number, 'count' => $count])
                ->values()
                ->all(),
            'calls_by_day' => collect($callsByDay)
                ->map(static fn (int $count, string $date): array => ['date' => $date, 'count' => $count])
                ->values()
                ->all(),
        ];
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
