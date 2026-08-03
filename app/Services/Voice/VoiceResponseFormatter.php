<?php

namespace App\Services\Voice;

/**
 * Turns raw LLM output into text that is safe and sensible to hand to Twilio's <Say>.
 *
 * XML escaping deliberately does NOT happen here — it belongs to the TwiML builder so that text is
 * escaped exactly once, at render time.
 */
class VoiceResponseFormatter
{
    /**
     * Read aloud, a URL is noise. Replace rather than delete so the sentence still parses.
     */
    private const URL_REPLACEMENT = 'the link on our website';

    /**
     * @var array<string, string>
     */
    private const SPOKEN_REPLACEMENTS = [
        'e.g.' => 'for example',
        'i.e.' => 'that is',
        'etc.' => 'and so on',
        'vs.' => 'versus',
        'FAQ' => 'F A Q',
        '&' => ' and ',
        '=>' => ' leads to ',
        '->' => ' leads to ',
    ];

    public function format(string $answer, ?int $maxChars = null): string
    {
        $maxChars ??= (int) config('rag.voice.max_answer_chars');

        $text = $this->stripMarkdown($answer);
        $text = $this->replaceUnspeakable($text);
        $text = $this->collapseWhitespace($text);
        $text = $this->truncateAtSentence($text, $maxChars);

        return trim($text);
    }

    private function stripMarkdown(string $text): string
    {
        // Fenced code blocks: unreadable aloud, drop the payload entirely.
        $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;
        $text = preg_replace('/~~~.*?~~~/s', ' ', $text) ?? $text;

        // Markdown links: keep the label, discard the target.
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text) ?? $text;

        // Images.
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text) ?? $text;

        // Emphasis, inline code, strikethrough.
        $text = preg_replace('/(\*\*|__|\*|_|`|~~)/', '', $text) ?? $text;

        // ATX headings and blockquote markers at line starts.
        $text = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}>\s?/m', '', $text) ?? $text;

        // List markers become sentence breaks so the speech does not run together.
        $text = preg_replace('/^\s*[-*+]\s+/m', '. ', $text) ?? $text;
        $text = preg_replace('/^\s*\d+[.)]\s+/m', '. ', $text) ?? $text;

        // Horizontal rules and table pipes.
        $text = preg_replace('/^\s*([-=_*])\1{2,}\s*$/m', ' ', $text) ?? $text;

        return str_replace('|', ' ', $text);
    }

    private function replaceUnspeakable(string $text): string
    {
        // URLs and bare hosts.
        $text = preg_replace('#\bhttps?://\S+#i', self::URL_REPLACEMENT, $text) ?? $text;
        $text = preg_replace('#\bwww\.\S+#i', self::URL_REPLACEMENT, $text) ?? $text;

        // Windows and UNC paths, then POSIX paths with at least two segments.
        $text = preg_replace('#\b[A-Za-z]:\\\\[^\s,;]+#', 'a file on our system', $text) ?? $text;
        $text = preg_replace('#(?<![\w.])/(?:[\w.-]+/){1,}[\w.-]+#', 'a file on our system', $text) ?? $text;

        // Retrieval artefacts that can leak from the context block.
        $text = preg_replace('/\[chunk:\d+[^\]]*\]/i', '', $text) ?? $text;

        foreach (self::SPOKEN_REPLACEMENTS as $needle => $replacement) {
            $text = str_ireplace($needle, $replacement, $text);
        }

        return $text;
    }

    private function collapseWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        // Tidy up the punctuation the list-marker rewrite above can leave behind.
        $text = preg_replace('/\s+([.,;:!?])/u', '$1', $text) ?? $text;
        $text = preg_replace('/([.,;:!?])\s*\1+/u', '$1', $text) ?? $text;
        $text = preg_replace('/^[.\s]+/u', '', $text) ?? $text;

        return $text;
    }

    /**
     * A single <Say> caps at 4096 characters, and anything close to that is far too long to listen
     * to, so cut at the last sentence boundary that fits rather than mid-word.
     */
    private function truncateAtSentence(string $text, int $maxChars): string
    {
        if ($maxChars <= 0 || mb_strlen($text) <= $maxChars) {
            return $text;
        }

        $slice = mb_substr($text, 0, $maxChars);
        $lastBoundary = max(
            (int) mb_strrpos($slice.' ', '. '),
            (int) mb_strrpos($slice.' ', '! '),
            (int) mb_strrpos($slice.' ', '? '),
        );

        if ($lastBoundary > (int) ($maxChars * 0.4)) {
            return mb_substr($slice, 0, $lastBoundary + 1);
        }

        $lastSpace = mb_strrpos($slice, ' ');

        return ($lastSpace !== false ? mb_substr($slice, 0, $lastSpace) : $slice).'.';
    }
}
