<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSubtopic;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Everything the results page shows, read from what was frozen at submit. No page
 * view ever re-scores 110 items: the grader wrote the answer rows and the subtopic
 * rollup once, and this class only aggregates them.
 *
 * An unsubmitted attempt has no stored score, so a report built on one is
 * meaningless by construction — the controller refuses to build it.
 */
final class ScoreReport
{
    public function __construct(private readonly ExamAttempt $attempt) {}

    public function total(): int
    {
        return (int) $this->attempt->total_score;
    }

    public function maximum(): int
    {
        return (int) config('cpns.total_max');
    }

    public function passed(): bool
    {
        return $this->attempt->hasPassed();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function subtests(): array
    {
        $rollup = $this->attempt->subtopics->groupBy(fn (ExamAttemptSubtopic $row): string => $row->category->value);

        $subtests = [];

        foreach (QuestionCategory::cases() as $category) {
            $rows = $rollup->get($category->value, new Collection);
            $score = $this->attempt->scoreFor($category);
            $max = $category->maxScore();
            $threshold = $category->threshold();

            $subtests[$category->value] = [
                'key' => $category->value,
                'label' => $category->label(),
                'name' => $category->name(),
                'score' => $score,
                'max' => $max,
                'threshold' => $threshold,
                'passed' => $score >= $threshold,
                'shortfall' => max(0, $threshold - $score),
                'correct' => (int) $rows->sum('correct'),
                'wrong' => (int) $rows->sum('wrong'),
                'blank' => (int) $rows->sum('blank'),
                'percent' => round($score / $max * 100, 1),
                'threshold_percent' => round($threshold / $max * 100, 1),
                'has_key' => $category->requiresKey(),
                // TKP has no wrong answers, so its average option weight is what
                // shows whether the participant picks defensible options.
                'average_weight' => $category === QuestionCategory::Tkp ? $this->averageTkpWeight() : null,
            ];
        }

        return $subtests;
    }

    public function averageTkpWeight(): float
    {
        $weights = $this->attempt->answers()
            ->whereNotNull('selected_option_id')
            ->whereHas('question', fn ($query) => $query->where('category', QuestionCategory::Tkp->value))
            ->pluck('score_awarded');

        return $weights->isEmpty() ? 0.0 : round((float) $weights->avg(), 2);
    }

    /**
     * Weakest first, because this is the section that answers "what do I study".
     *
     * One metric for all three subtests: points captured against points available.
     * TKP writes its *answered* count into the stored `correct` column and never
     * writes `wrong`, so `correct / (correct + wrong + blank)` would call a paper of
     * weight-1 answers 100% and bury the one subtest with no key. The stored `score`
     * has no such blind spot: a keyed subtopic scores 5 per right answer and a TKP
     * subtopic scores the chosen option's weight, so both land on the same 0-5 per
     * item scale this way.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function subtopics(): Collection
    {
        return $this->attempt->subtopics()
            ->get()
            ->map(function (ExamAttemptSubtopic $row): array {
                $total = $row->correct + $row->wrong + $row->blank;
                $available = $total * $row->category->perItemMaximum();

                return [
                    'label' => $row->label(),
                    'category' => $row->category->label(),
                    'correct' => $row->correct,
                    'wrong' => $row->wrong,
                    'blank' => $row->blank,
                    'total' => $total,
                    'score' => $row->score,
                    'available' => $available,
                    'ratio' => $available === 0 ? 0.0 : round($row->score / $available * 100, 1),
                ];
            })
            ->sortBy('ratio')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function timing(): array
    {
        $answers = $this->attempt->answers()->with('question.options')->get();
        $budget = (int) $this->attempt->package->duration_minutes * 60;

        $endedAt = $this->attempt->submitted_at ?? now();
        $used = min($budget, max(0, $endedAt->getTimestamp() - $this->attempt->started_at->getTimestamp()));

        $slowest = $answers
            ->sortByDesc('seconds_spent')
            ->take(5)
            ->map(fn ($answer): array => [
                'label' => $answer->question->subcategoryLabel(),
                'stem' => $answer->question->stem,
                'seconds' => $answer->seconds_spent,
            ])
            ->values();

        // Answered fast and wrong is near-certainly a guess. TKP items are never
        // wrong, so they cannot appear here; the UI says so.
        $guesses = $answers
            ->filter(fn ($answer): bool => $answer->is_correct === false
                && $answer->selected_option_id !== null
                && $answer->seconds_spent < (int) config('cpns.guess_threshold_seconds'))
            ->count();

        return [
            'used_seconds' => $used,
            'budget_seconds' => $budget,
            'used_percent' => $budget === 0 ? 0.0 : round($used / $budget * 100, 1),
            'average_seconds' => $answers->isEmpty() ? 0 : (int) round($answers->avg('seconds_spent')),
            'slowest' => $slowest,
            'guesses' => $guesses,
            'guess_threshold' => (int) config('cpns.guess_threshold_seconds'),
            'is_estimate' => true,
        ];
    }

    /**
     * Every sitting of this participant on this package, oldest first, so the chart
     * and the table tell the story of their own progress and nobody else's.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        $attempts = ExamAttempt::query()
            ->where('user_id', $this->attempt->user_id)
            ->where('exam_package_id', $this->attempt->exam_package_id)
            ->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Expired->value])
            ->orderBy('submitted_at')
            // Two sittings graded in the same second must still order oldest-first,
            // so the chart and the delta column never swap a tie at random.
            ->orderBy('id')
            ->get();

        $rows = [];
        $previous = null;

        foreach ($attempts as $attempt) {
            $rows[] = [
                'id' => $attempt->id,
                'at' => $attempt->submitted_at,
                'total' => (int) $attempt->total_score,
                'twk' => (int) $attempt->twk_score,
                'tiu' => (int) $attempt->tiu_score,
                'tkp' => (int) $attempt->tkp_score,
                'status' => $attempt->status,
                'delta' => $previous === null ? null : (int) $attempt->total_score - $previous,
                'is_current' => $attempt->id === $this->attempt->id,
            ];

            $previous = (int) $attempt->total_score;
        }

        return $rows;
    }

    /**
     * Chart geometry for a server-rendered SVG. The project has no chart library
     * and this is a handful of points and lines, so 200KB of JavaScript would not
     * be proportionate.
     *
     * @return array<string, mixed>
     */
    public function chart(): array
    {
        $history = $this->history();
        $width = 640;
        $height = 200;
        $padding = 28;
        $scale = fn (int $value): float => $height - $padding - ($value / max(1, $this->maximum())) * ($height - 2 * $padding);
        $count = count($history);
        $step = $count > 1 ? ($width - 2 * $padding) / ($count - 1) : 0;

        $definitions = [
            ['key' => 'total', 'label' => 'Total', 'color' => '#0657a8', 'threshold' => null],
            ['key' => 'twk', 'label' => 'TWK', 'color' => '#15803d', 'threshold' => QuestionCategory::Twk->threshold()],
            ['key' => 'tiu', 'label' => 'TIU', 'color' => '#b45309', 'threshold' => QuestionCategory::Tiu->threshold()],
            ['key' => 'tkp', 'label' => 'TKP', 'color' => '#c2410c', 'threshold' => QuestionCategory::Tkp->threshold()],
        ];

        $series = [];

        foreach ($definitions as $definition) {
            $points = [];

            foreach ($history as $index => $row) {
                $points[] = round($padding + $index * $step, 1).','.round($scale((int) $row[$definition['key']]), 1);
            }

            $series[] = $definition + [
                'points' => implode(' ', $points),
                'threshold_y' => $definition['threshold'] === null ? null : round($scale($definition['threshold']), 1),
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'padding' => $padding,
            'series' => $series,
            'count' => $count,
        ];
    }

    /**
     * All 110 items with the participant's answer, the key and the explanation,
     * filterable to all / wrong / blank / correct / answered. This is where the
     * learning actually happens, and it is why explanations open at submit.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function review(string $filter = 'all', ?QuestionCategory $category = null): Collection
    {
        $questions = $this->attempt->package->questions()->with('options')->get();
        $answers = $this->attempt->answers()->get()->keyBy('question_id');

        $rows = $questions->values()->map(function (Question $question, int $index) use ($answers): array {
            $answer = $answers->get($question->id);
            $selected = $answer?->selected_option_id;

            $status = match (true) {
                $selected === null => 'blank',
                $question->category->requiresKey() => $answer->is_correct ? 'correct' : 'wrong',
                // TKP has no key: an answer is answered, never "benar". Its own
                // status is what keeps it out of the Benar and Salah filters.
                default => 'answered',
            };

            return [
                'number' => $index + 1,
                'question' => $question,
                'answer' => $answer,
                'status' => $status,
            ];
        });

        return $rows
            ->when($category !== null, fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => $row['question']->category === $category,
            ))
            ->when($filter !== 'all', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => $row['status'] === $filter,
            ))
            ->values();
    }
}
