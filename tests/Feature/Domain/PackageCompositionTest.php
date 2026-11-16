<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\QuestionCategory;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Support\PackageComposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PackageCompositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_package_holds_exactly_thirty_thirty_five_forty_five(): void
    {
        $package = ExamPackage::factory()->complete()->create();

        $report = PackageComposition::report($package);

        foreach (['twk' => 30, 'tiu' => 35, 'tkp' => 45] as $category => $expected) {
            $this->assertSame($expected, $report[$category]['present'], "{$category} count");
            $this->assertTrue($report[$category]['complete']);
        }

        $this->assertSame(110, $package->questions()->count());
        $this->assertTrue(PackageComposition::isComplete($package));
        $this->assertSame('', PackageComposition::shortfallMessage($package));
    }

    public function test_a_package_one_item_short_is_not_complete(): void
    {
        $package = ExamPackage::factory()->complete()->create();
        $package->questions()->detach($package->questions()->first()->id);

        $this->assertFalse(PackageComposition::isComplete($package));
        $this->assertSame('TWK kurang 1.', PackageComposition::shortfallMessage($package));
    }

    public function test_a_wrong_category_split_is_not_complete_even_at_one_hundred_and_ten_items(): void
    {
        $package = ExamPackage::factory()->create();

        foreach (['twk' => 31, 'tiu' => 34, 'tkp' => 45] as $category => $count) {
            $enum = QuestionCategory::from($category);
            for ($i = 0; $i < $count; $i++) {
                $package->addQuestion(Question::factory()->category($enum)->create());
            }
        }

        $this->assertSame(110, $package->questions()->count());
        $this->assertFalse(PackageComposition::isComplete($package));
        $this->assertSame('TWK lebih 1, TIU kurang 1.', PackageComposition::shortfallMessage($package));
    }

    public function test_positions_are_assigned_in_attach_order_and_a_question_never_repeats(): void
    {
        $package = ExamPackage::factory()->create();
        $first = Question::factory()->twk()->create();
        $second = Question::factory()->twk()->create();

        $package->addQuestion($first);
        $package->addQuestion($second);

        $this->assertSame([1, 2], $package->questions()->get()->pluck('pivot.position')->all());
        $this->assertSame([$first->id, $second->id], $package->questions()->get()->pluck('id')->all());
    }
}
