<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\QuestionCategory;
use App\Models\QuestionOption;
use App\Support\QuestionBank;
use Tests\TestCase;

/**
 * The bank that actually ships. Everything the loader cannot check lives here: the
 * blueprint's size, the kisi-kisi coverage, and — most importantly — that the answer
 * is not always the same letter.
 *
 * A bank whose key is always A is worse than a broken one: it looks perfect to every
 * test that only counts items, and a participant who notices scores full marks
 * without reading a single question.
 */
final class ShippedQuestionBankTest extends TestCase
{
    public function test_it_carries_exactly_the_blueprint(): void
    {
        $items = app(QuestionBank::class)->items();

        $this->assertCount((int) config('cpns.total_items'), $items);

        foreach (QuestionCategory::cases() as $category) {
            $this->assertCount(
                $category->itemCount(),
                $this->itemsOf($category),
                $category->label().' harus genap '.$category->itemCount().' butir.',
            );
        }
    }

    public function test_every_kisi_kisi_subtopic_has_items(): void
    {
        foreach (QuestionCategory::cases() as $category) {
            $covered = array_unique(array_map(
                static fn (array $item): string => $item['subcategory'],
                $this->itemsOf($category),
            ));

            foreach (array_keys($category->subcategories()) as $subcategory) {
                $this->assertContains(
                    $subcategory,
                    $covered,
                    $category->label().' belum punya butir untuk submateri '.$subcategory.'.',
                );
            }
        }
    }

    public function test_the_answer_does_not_always_sit_on_the_same_letter(): void
    {
        foreach ([QuestionCategory::Twk, QuestionCategory::Tiu] as $category) {
            $keys = $this->keysOf($category);

            foreach (QuestionOption::LETTERS as $letter) {
                $this->assertGreaterThanOrEqual(
                    4,
                    $keys[$letter] ?? 0,
                    $category->label().': kunci '.$letter.' terlalu jarang, sebarannya miring.',
                );
            }

            // A run of identical letters lets a participant start guessing the pattern
            // instead of answering the question.
            preg_match_all('/(.)\1*/', implode('', array_column($this->itemsOf($category), 'key')), $matches);

            $this->assertLessThanOrEqual(
                2,
                max(array_map('strlen', $matches[0])),
                $category->label().': kunci berulang terlalu panjang, polanya bisa ditebak.',
            );
        }
    }

    public function test_the_heaviest_tkp_option_is_not_always_the_same_letter(): void
    {
        $best = [];

        foreach ($this->itemsOf(QuestionCategory::Tkp) as $item) {
            foreach ($item['weights'] as $letter => $weight) {
                if ($weight === $item['category']->perItemMaximum()) {
                    $best[$letter] = ($best[$letter] ?? 0) + 1;
                }
            }
        }

        foreach (QuestionOption::LETTERS as $letter) {
            $this->assertGreaterThanOrEqual(
                4,
                $best[$letter] ?? 0,
                'Opsi berbobot tertinggi di huruf '.$letter.' terlalu jarang, sebarannya miring.',
            );
        }
    }

    /**
     * Every item is readable: a participant meets the stem before the options, and an
     * explanation explains. The loader enforces a floor; this asserts the shipped bank
     * is well clear of it.
     */
    public function test_every_item_reads_like_a_question(): void
    {
        foreach (app(QuestionBank::class)->items() as $item) {
            $this->assertGreaterThanOrEqual(20, mb_strlen($item['stem']), $item['code'].': teks soal terlalu pendek.');
            $this->assertGreaterThanOrEqual(80, mb_strlen($item['explanation']), $item['code'].': pembahasan terlalu tipis untuk mengajar.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemsOf(QuestionCategory $category): array
    {
        return array_values(array_filter(
            app(QuestionBank::class)->items(),
            static fn (array $item): bool => $item['category'] === $category,
        ));
    }

    /**
     * @return array<string, int>
     */
    private function keysOf(QuestionCategory $category): array
    {
        $keys = [];

        foreach ($this->itemsOf($category) as $item) {
            $letter = (string) $item['key'];
            $keys[$letter] = ($keys[$letter] ?? 0) + 1;
        }

        return $keys;
    }
}
