<?php

namespace Tests\Unit;

use App\Services\Voice\VoiceResponseFormatter;
use Tests\TestCase;

class VoiceResponseFormatterTest extends TestCase
{
    private VoiceResponseFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new VoiceResponseFormatter;
    }

    public function test_it_strips_bold_italic_and_inline_code(): void
    {
        $result = $this->formatter->format('**Opening hours** are _nine_ to `five`.');

        $this->assertSame('Opening hours are nine to five.', $result);
    }

    public function test_it_removes_headings_and_blockquotes(): void
    {
        $result = $this->formatter->format("## Hours\n> We are open daily.");

        $this->assertStringNotContainsString('#', $result);
        $this->assertStringNotContainsString('>', $result);
        $this->assertStringContainsString('We are open daily.', $result);
    }

    public function test_it_drops_fenced_code_blocks_entirely(): void
    {
        $result = $this->formatter->format("Use the API.\n```php\n\$x = 1;\n```\nThat is all.");

        $this->assertStringNotContainsString('$x', $result);
        $this->assertStringContainsString('Use the API.', $result);
        $this->assertStringContainsString('That is all.', $result);
    }

    public function test_it_turns_list_markers_into_sentence_breaks(): void
    {
        $result = $this->formatter->format("Options:\n- First option\n- Second option");

        $this->assertStringNotContainsString('-', $result);
        $this->assertStringContainsString('First option', $result);
        $this->assertStringContainsString('Second option', $result);
    }

    public function test_it_keeps_link_labels_and_discards_targets(): void
    {
        $result = $this->formatter->format('See [our pricing page](https://example.com/pricing).');

        $this->assertStringContainsString('our pricing page', $result);
        $this->assertStringNotContainsString('example.com', $result);
    }

    public function test_it_replaces_bare_urls_with_something_speakable(): void
    {
        $result = $this->formatter->format('Details at https://example.com/a/b?c=d and www.example.org.');

        $this->assertStringNotContainsString('https://', $result);
        $this->assertStringNotContainsString('www.', $result);
        $this->assertStringContainsString('the link on our website', $result);
    }

    public function test_it_replaces_file_paths(): void
    {
        $windows = $this->formatter->format('Open C:\\Users\\test\\report.pdf now.');
        $posix = $this->formatter->format('Open /var/www/html/report.pdf now.');

        $this->assertStringContainsString('a file on our system', $windows);
        $this->assertStringContainsString('a file on our system', $posix);
    }

    public function test_it_expands_ampersand_which_would_otherwise_break_twiml(): void
    {
        $result = $this->formatter->format('Sales & support are open.');

        $this->assertStringNotContainsString('&', $result);
        $this->assertStringContainsString('and', $result);
    }

    public function test_it_removes_retrieval_chunk_artefacts(): void
    {
        $result = $this->formatter->format('[chunk:42 | doc:hours.pdf] We open at nine.');

        $this->assertStringNotContainsString('chunk:', $result);
        $this->assertStringContainsString('We open at nine.', $result);
    }

    public function test_it_collapses_whitespace(): void
    {
        $result = $this->formatter->format("We  are\n\n\topen   daily.");

        $this->assertSame('We are open daily.', $result);
    }

    public function test_it_truncates_at_a_sentence_boundary(): void
    {
        $answer = 'First sentence here. Second sentence here. Third sentence here.';

        $result = $this->formatter->format($answer, 45);

        $this->assertStringEndsWith('.', $result);
        $this->assertLessThanOrEqual(45, mb_strlen($result));
        $this->assertStringContainsString('First sentence here.', $result);
        $this->assertStringNotContainsString('Third', $result);
    }

    public function test_it_never_cuts_mid_word_when_no_boundary_fits(): void
    {
        $result = $this->formatter->format('Supercalifragilistic expialidocious antidisestablishmentarianism follows', 30);

        $this->assertLessThanOrEqual(31, mb_strlen($result));
        $this->assertStringEndsWith('.', $result);
        $this->assertStringNotContainsString('antidisestablishmentarianis ', $result);
    }

    public function test_short_answers_pass_through_untouched(): void
    {
        $this->assertSame('We open at nine.', $this->formatter->format('We open at nine.', 500));
    }
}
