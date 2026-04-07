<?php

namespace App\Services\Rag;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\MessageCitation;
use App\Models\Project;

class ChatAnswerService
{
    public function __construct(
        private readonly OllamaEmbeddingService $embeddingService,
        private readonly ContextRetrievalService $retrievalService,
        private readonly OllamaGenerationService $generationService,
    ) {}

    /**
     * @return array{message:ChatMessage,citations:array<int,array<string,mixed>>}
     */
    public function answer(Project $project, ChatSession $chatSession, string $question): array
    {
        $embedding = $this->embeddingService->embed($question);
        $contexts = $this->retrievalService->retrieve($project, $embedding);

        $history = $chatSession->messages()
            ->latest('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): string => strtoupper($message->role).': '.$message->content)
            ->implode("\n");

        $contextBlock = collect($contexts)
            ->map(function (array $item): string {
                return sprintf(
                    "[chunk:%d | doc:%s | pages:%s-%s]\n%s",
                    $item['chunk_id'],
                    $item['document_name'] ?? 'unknown',
                    $item['page_from'] ?? '?',
                    $item['page_to'] ?? '?',
                    $item['content']
                );
            })
            ->implode("\n\n");

        $draft = $this->generationService->generate(
            "You are a retrieval-grounded assistant. Answer only from the provided context.\n".
            "If context is insufficient, clearly state what is missing.\n\n".
            "Conversation history:\n{$history}\n\n".
            "Question:\n{$question}\n\n".
            "Context:\n{$contextBlock}\n"
        );

        $normalized = $this->generationService->generate(
            "Rewrite the following assistant answer to sound natural, clear, and concise.\n".
            "Do not add new facts. Keep it grounded to context.\n".
            "If evidence is weak, explicitly say context is insufficient.\n\n".
            "Answer to normalize:\n{$draft}"
        );

        $assistantMessage = $chatSession->messages()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'role' => 'assistant',
            'content' => $normalized,
            'model' => config('rag.ollama.generation_model'),
            'metadata' => [
                'draft' => $draft,
                'retrieved_chunks' => count($contexts),
            ],
        ]);

        $citations = collect($contexts)->take(4)->map(function (array $item) use ($assistantMessage): array {
            MessageCitation::query()->create([
                'chat_message_id' => $assistantMessage->id,
                'document_chunk_id' => $item['chunk_id'],
                'score' => $item['score'],
                'metadata' => [
                    'document_name' => $item['document_name'],
                    'page_from' => $item['page_from'],
                    'page_to' => $item['page_to'],
                    'excerpt' => $item['excerpt'],
                ],
            ]);

            return [
                'chunk_id' => $item['chunk_id'],
                'document_name' => $item['document_name'],
                'page_from' => $item['page_from'],
                'page_to' => $item['page_to'],
                'excerpt' => $item['excerpt'],
                'score' => $item['score'],
            ];
        })->all();

        return [
            'message' => $assistantMessage,
            'citations' => $citations,
        ];
    }
}

