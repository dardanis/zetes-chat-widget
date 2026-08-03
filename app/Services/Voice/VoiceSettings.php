<?php

namespace App\Services\Voice;

use App\Models\Project;

/**
 * Per-project voice configuration, mirroring the widget settings default/serialize pair in
 * ProjectController. Lives in a dedicated class because the webhook controller, the admin
 * controller, and the answering job all need it.
 */
class VoiceSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'greeting' => 'Hello, thanks for calling. How can I help you today?',
            'tts_voice' => (string) config('rag.voice.default_tts_voice'),
            // BCP-47 for Twilio (en-US), deliberately separate from the widget's 2-letter code.
            'language' => (string) config('rag.voice.default_language'),
            'speech_timeout' => 'auto',
            'max_turns' => (int) config('rag.voice.max_turns'),
            'thinking_message' => 'One moment while I look that up.',
            'no_input_prompt' => "Sorry, I didn't catch that. Could you repeat your question?",
            'fallback_message' => "I'm having trouble right now. Please try again later.",
            'goodbye_message' => 'Thanks for calling. Goodbye.',
            'unavailable_message' => 'Sorry, this number is not available right now. Goodbye.',
            'record_calls' => false,
            'transfer_number' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(Project $project): array
    {
        $settings = is_array($project->voice_settings) ? $project->voice_settings : [];

        return array_merge(self::defaults(), $settings);
    }

    /**
     * Voice settings plus read-only context for the admin UI.
     *
     * @return array<string, mixed>
     */
    public static function serialize(Project $project): array
    {
        return array_merge(self::for($project), [
            'phone_number' => $project->phone_number,
            'twilio_phone_sid' => $project->twilio_phone_sid,
            'project_name' => $project->name,
        ]);
    }

    /**
     * Speech recognition hints materially improve accuracy on domain vocabulary, so seed them
     * from the project's own document names and configured suggested questions.
     *
     * @return list<string>
     */
    public static function speechHints(Project $project): array
    {
        $widgetSettings = is_array($project->widget_settings) ? $project->widget_settings : [];
        $suggested = is_array($widgetSettings['suggested_questions'] ?? null)
            ? $widgetSettings['suggested_questions']
            : [];

        $documentNames = $project->documents()
            ->where('status', 'indexed')
            ->orderBy('original_name')
            ->limit(100)
            ->pluck('original_name')
            ->all();

        return collect($documentNames)
            ->map(static fn (mixed $name): string => (string) pathinfo((string) $name, PATHINFO_FILENAME))
            ->merge($suggested)
            ->flatMap(static fn (string $phrase): array => preg_split('/[^\p{L}\p{N}\'\-]+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(static fn (string $term): string => trim($term))
            ->filter(static fn (string $term): bool => mb_strlen($term) >= 4)
            ->unique(static fn (string $term): string => mb_strtolower($term))
            ->take((int) config('rag.voice.max_speech_hints'))
            ->values()
            ->all();
    }
}
