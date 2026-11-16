<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ExamAttempt;
use App\Models\Question;

/**
 * Answering a 110-item paper by hand in every test would bury the assertions, so
 * tests describe the sitting in one of four strategies and this trait does the
 * clicking. `best` = the key for TWK/TIU and weight 5 for TKP; `worst` = a wrong
 * option for TWK/TIU and weight 1 for TKP; `wrong` = only the misses are filled
 * in; `blank` = every item is opened and left empty.
 */
trait SitsExam
{
    protected function answerEveryQuestion(ExamAttempt $attempt, string $strategy = 'best'): void
    {
        foreach ($attempt->package->questions()->with('options')->get() as $question) {
            $option = $this->optionFor($question, $strategy);

            $attempt->answers()->updateOrCreate(
                ['question_id' => $question->id],
                [
                    'selected_option_id' => $option?->id,
                    'answered_at' => now(),
                ],
            );
        }
    }

    /** Fill in exactly the first $count items of the given subtest, correctly. */
    protected function answerCorrectly(ExamAttempt $attempt, string $category, int $count): void
    {
        $questions = $attempt->package->questions()
            ->where('questions.category', $category)
            ->with('options')
            ->limit($count)
            ->get();

        foreach ($questions as $question) {
            $attempt->answers()->updateOrCreate(
                ['question_id' => $question->id],
                [
                    'selected_option_id' => $question->options->firstWhere('is_correct', true)?->id,
                    'answered_at' => now(),
                ],
            );
        }
    }

    private function optionFor(Question $question, string $strategy): mixed
    {
        return match ($strategy) {
            'blank' => null,
            'best' => $question->category->requiresKey()
                ? $question->options->firstWhere('is_correct', true)
                : $question->options->sortByDesc('score')->first(),
            'worst' => $question->category->requiresKey()
                ? $question->options->firstWhere('is_correct', false)
                : $question->options->sortBy('score')->first(),
            'wrong' => $question->category->requiresKey()
                ? $question->options->firstWhere('is_correct', false)
                : null,
            default => $question->options->first(),
        };
    }
}
