<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\DB;

/**
 * Scoring happens here and nowhere else, exactly once per sitting.
 *
 * TWK and TIU award the blueprint's per-correct score for the keyed option and
 * zero otherwise. TKP awards the chosen option's weight (1-5) and zero when
 * nothing was chosen — which is why blanking a TKP item is always a loss and
 * never a safe move.
 *
 * The result is frozen onto the answer rows and rolled up per subtopic. A later
 * edit to a question therefore cannot move a finished participant's score, while
 * the item's text and explanation stay live in the bank.
 *
 * TKP has no wrong answers, so for a TKP subtopic the `correct` column counts
 * answered items and `wrong` stays 0. The results page labels it that way.
 */
final class ExamGrader
{
    public function grade(ExamAttempt $attempt, bool $expired = false): ExamAttempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }

        $questions = $attempt->package->questions()->with('options')->get();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        /** @var array<string, int> $scores */
        $scores = [
            QuestionCategory::Twk->value => 0,
            QuestionCategory::Tiu->value => 0,
            QuestionCategory::Tkp->value => 0,
        ];

        /** @var array<string, array<string, mixed>> $rollup */
        $rollup = [];

        foreach ($questions as $question) {
            $category = $question->category;
            $bucketKey = $category->value.'|'.$question->subcategory;

            $rollup[$bucketKey] ??= [
                'category' => $category->value,
                'subcategory' => $question->subcategory,
                'correct' => 0,
                'wrong' => 0,
                'blank' => 0,
                'score' => 0,
            ];

            $answer = $answers->get($question->id);
            $option = ($answer !== null && $answer->selected_option_id !== null)
                ? $question->options->firstWhere('id', $answer->selected_option_id)
                : null;

            if ($option === null) {
                $rollup[$bucketKey]['blank']++;

                $answer?->forceFill(['is_correct' => null, 'score_awarded' => 0])->save();

                continue;
            }

            $awarded = $this->awardedScore($category, $option);

            if ($category->requiresKey()) {
                $isCorrect = $option->is_correct === true;

                $rollup[$bucketKey][$isCorrect ? 'correct' : 'wrong']++;
                $answer->forceFill(['is_correct' => $isCorrect, 'score_awarded' => $awarded])->save();
            } else {
                // TKP has no key at all: an answer is answered, never "correct".
                $rollup[$bucketKey]['correct']++;
                $answer->forceFill(['is_correct' => null, 'score_awarded' => $awarded])->save();
            }

            $rollup[$bucketKey]['score'] += $awarded;
            $scores[$category->value] += $awarded;
        }

        DB::transaction(function () use ($attempt, $rollup, $scores, $expired): void {
            $attempt->subtopics()->delete();
            $attempt->subtopics()->createMany(array_values($rollup));

            $attempt->forceFill([
                'status' => $expired ? AttemptStatus::Expired : AttemptStatus::Submitted,
                'submitted_at' => now(),
                // Freeing the unique key is what lets the participant retake it.
                'active_key' => null,
                'twk_score' => $scores[QuestionCategory::Twk->value],
                'tiu_score' => $scores[QuestionCategory::Tiu->value],
                'tkp_score' => $scores[QuestionCategory::Tkp->value],
                'total_score' => array_sum($scores),
            ])->save();
        });

        return $attempt->refresh();
    }

    /**
     * What one chosen option is worth. The two subtests are graded by different
     * rules, so this branches on `requiresKey()` rather than on a category name:
     * asking `cpns.subtests.tkp.correct` for a per-correct score would read the
     * null that config deliberately holds and silently award TKP items zero.
     */
    private function awardedScore(QuestionCategory $category, QuestionOption $option): int
    {
        if (! $category->requiresKey()) {
            return (int) ($option->score ?? 0);
        }

        // A keyed item whose bank row marks no correct option scores nothing:
        // the chosen option's own flag is null, so it lands on the wrong branch
        // instead of the grader insisting a key must exist.
        return $option->is_correct === true
            ? $category->scorePerCorrect()
            : (int) $category->config('wrong', 0);
    }
}
