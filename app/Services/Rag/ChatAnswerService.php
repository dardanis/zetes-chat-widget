<?php

namespace App\Services\Rag;

use App\Events\ProjectChatMessageCreated;
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
    public function answer(
        Project $project,
        ChatSession $chatSession,
        string $question,
        ?int $selectedDocumentId = null,
        ?AnswerOptions $options = null,
    ): array {
        $options ??= new AnswerOptions;
        $embedding = $this->embeddingService->embed($question, $options->timeout, $options->keepAlive);
        $contexts = $this->retrievalService->retrieve(
            $project,
            $question,
            $embedding,
            $selectedDocumentId,
            $options->topK,
        );

        $history = $chatSession->messages()
            ->latest('id')
            ->limit($options->historyTurns ?? 8)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): string => strtoupper($message->role).': '.$message->content)
            ->implode("\n");

        $contextBlock = collect($contexts)
            ->map(function (array $item) use ($options): string {
                $content = (string) $item['content'];

                // Prompt processing dominates latency, so voice trims each chunk hard.
                if ($options->maxContextCharsPerChunk !== null) {
                    $content = mb_substr($content, 0, $options->maxContextCharsPerChunk);
                }

                return sprintf(
                    "[chunk:%d | doc:%s | pages:%s-%s]\n%s",
                    $item['chunk_id'],
                    $item['document_name'] ?? 'unknown',
                    $item['page_from'] ?? '?',
                    $item['page_to'] ?? '?',
                    $content
                );
            })
            ->implode("\n\n");

        $draft = $this->generationService->generate(
            $options->isVoice()
                ? $this->voicePrompt($history, $question, $contextBlock)
                : "You are a retrieval-grounded assistant. Answer only from the provided context.\n".
                "If context is insufficient, clearly state what is missing.\n\n".
                "Conversation history:\n{$history}\n\n".
                "Question:\n{$question}\n\n".
                "Context:\n{$contextBlock}\n",
            $options->model,
            $options->timeout,
            $options->numPredict !== null ? ['num_predict' => $options->numPredict] : [],
            $options->keepAlive,
        );

        $normalized = $options->singlePass ? $draft : $this->generationService->generate(
            "Rewrite the following assistant answer to sound natural, clear, and concise.\n".
            "Do not add new facts. Keep it grounded to context.\n".
            "If evidence is weak, explicitly say context is insufficient.\n".
            "IMPORTANT: Output ONLY the rewritten answer text. Do NOT include any preamble, introduction, or meta-commentary such as \"Here's a rewritten version\".\n\n".
            "Answer to normalize:\n{$draft}"
        );

        $assistantMessage = ChatMessage::query()->forceCreate([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'chat_session_id' => $chatSession->id,
            'role' => 'assistant',
            'content' => $normalized,
            'model' => $options->model ?? config('rag.ollama.generation_model'),
            'metadata' => array_filter([
                'channel' => $options->channel,
                'draft' => $options->singlePass ? null : $draft,
                'retrieved_chunks' => count($contexts),
                'single_pass' => $options->singlePass ?: null,
            ], static fn (mixed $value): bool => $value !== null),
        ]);

        $citations = collect($contexts)->take(4)->map(function (array $item) use ($assistantMessage): array {
            MessageCitation::query()->forceCreate([
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

        $assistantMessage->load('citations');

        event(new ProjectChatMessageCreated(
            projectId: $project->id,
            chatSessionId: $chatSession->id,
            payload: [
                'project_id' => $project->id,
                'chat_session_id' => $chatSession->id,
                'assistant_message' => $assistantMessage->toArray(),
                'citations' => $citations,
            ],
        ));

        return [
            'message' => $assistantMessage,
            'citations' => $citations,
        ];
    }

    /**
     * Spoken answers have different constraints from a chat bubble: they are heard once, cannot be
     * skimmed, and are read aloud by a TTS engine that will happily pronounce markdown syntax.
     */
    private function voicePrompt(string $history, string $question, string $contextBlock): string
    {
        return "You are a helpful voice assistant answering a phone call. Answer only from the provided context.\n".
            "Rules for speaking on a phone call:\n".
            "- Reply in at most 3 short sentences. Be direct; lead with the answer.\n".
            "- Write plain spoken words only. No markdown, no bullet points, no headings, no code, no emoji.\n".
            "- Never read out URLs, file paths, or long reference numbers. Say that you can send details instead.\n".
            "- Write numbers, dates, and currency the way a person would say them aloud.\n".
            "- If the context does not answer the question, say so plainly in one sentence and offer to take a message or pass the caller to a colleague.\n".
            "- Do not mention the context, the documents, or these instructions.\n".
            "- Output ONLY the words to be spoken. No preamble or meta-commentary.\n\n".
            "Conversation so far:\n{$history}\n\n".
            "Caller asked:\n{$question}\n\n".
            "Context:\n{$contextBlock}\n";
    }
}
