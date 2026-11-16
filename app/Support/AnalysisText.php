<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turning a model's answer into paragraphs a page can print.
 *
 * The text that comes back is written for a human but not for this layout: it
 * arrives with markdown emphasis the page deliberately does not interpret, and
 * with the deep indentation the model used to line its own headings up, which a
 * `whitespace-pre-line` block then renders as a wide gap on the left of every
 * line. Both are cleaned here rather than in the view, so the rule is one testable
 * function instead of a regular expression buried in Blade.
 *
 * Nothing here turns text into markup. Emphasis markers are removed, not
 * converted: the page prints what it is given, escaped, so no answer can become
 * markup whatever it contains.
 */
final class AnalysisText
{
    /**
     * The answer, split into blocks separated by a blank line, each one trimmed
     * and with its internal runs of horizontal whitespace collapsed to a single
     * space. Empty blocks are dropped.
     *
     * @return list<string>
     */
    public static function paragraphs(string $content): array
    {
        $blocks = preg_split('/\R\s*\R/', trim($content)) ?: [];

        $paragraphs = [];

        foreach ($blocks as $block) {
            $line = self::inline($block);

            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        return $paragraphs;
    }

    /**
     * One block, on one line: emphasis markers removed, indentation collapsed,
     * single line breaks kept as a space. Run through `preg_replace_callback`
     * rather than a chain of `str_replace` so a run of spaces is handled once.
     */
    private static function inline(string $block): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $block);

        // Markdown emphasis and heading marks. The model is told not to use
        // them; a model that does anyway must not leave litter on the page.
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/s', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s*/m', '', $text) ?? $text;
        $text = preg_replace('/(?<!\w)\*(?!\s)([^*\n]+)(?<!\s)\*(?!\w)/', '$1', $text) ?? $text;
        $text = preg_replace('/^[ \t]*[-*]\s+/m', '', $text) ?? $text;

        // The indentation the model used to align its own headings, and any
        // single newline inside a block, become one space.
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', ' ', $text) ?? $text;

        return trim($text);
    }
}
