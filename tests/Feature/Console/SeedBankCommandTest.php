<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Support\PackageComposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SeedBankCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'tryout-skd-1';

    public function test_it_writes_the_bank_and_publishes_a_sit_ready_package(): void
    {
        $this->artisan('cpns:seed-bank')->assertSuccessful();

        $this->assertSame((int) config('cpns.total_items'), Question::query()->count());

        $package = ExamPackage::query()->where('slug', self::SLUG)->firstOrFail();

        $this->assertTrue($package->isPublished());
        $this->assertSame((int) config('cpns.duration_minutes'), $package->duration_minutes);
        $this->assertTrue(PackageComposition::isComplete($package));
        $this->assertSame(
            (int) config('cpns.total_items'),
            DB::table('exam_package_question')->where('exam_package_id', $package->id)->count(),
        );

        // The blueprint order, numbered without a gap: the pivot carries unique
        // indexes on both the item and the position.
        $this->assertSame(
            range(1, (int) config('cpns.total_items')),
            DB::table('exam_package_question')
                ->where('exam_package_id', $package->id)
                ->orderBy('position')
                ->pluck('position')
                ->all(),
        );
    }

    public function test_the_sitting_opens_with_twk_and_ends_with_tkp(): void
    {
        $this->artisan('cpns:seed-bank')->assertSuccessful();

        $categories = ExamPackage::query()
            ->where('slug', self::SLUG)
            ->firstOrFail()
            ->questions()
            ->get()
            ->map(static fn (Question $question): string => $question->category->value)
            ->all();

        $this->assertSame('twk', $categories[0]);
        $this->assertSame('tkp', $categories[count($categories) - 1]);
    }

    public function test_running_it_again_updates_instead_of_appending(): void
    {
        $this->artisan('cpns:seed-bank')->assertSuccessful();

        $before = Question::query()->orderBy('code')->pluck('id', 'code')->all();

        $this->artisan('cpns:seed-bank')->assertSuccessful();

        $this->assertSame((int) config('cpns.total_items'), Question::query()->count());
        $this->assertSame($before, Question::query()->orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame(1, ExamPackage::query()->where('slug', self::SLUG)->count());
    }

    public function test_an_edited_bank_file_reaches_the_item_it_names(): void
    {
        $this->artisan('cpns:seed-bank')->assertSuccessful();

        $question = Question::query()->where('code', 'TWK-001')->firstOrFail();
        $question->forceFill(['explanation' => 'Diubah langsung di database.'])->save();

        $this->artisan('cpns:seed-bank')->assertSuccessful();

        $this->assertNotSame(
            'Diubah langsung di database.',
            Question::query()->where('code', 'TWK-001')->firstOrFail()->explanation,
        );
    }

    public function test_it_leaves_a_sat_package_composition_alone(): void
    {
        $package = ExamPackage::factory()->create(['slug' => self::SLUG, 'title' => 'Tryout SKD #1']);
        $question = Question::factory()->create();
        $package->questions()->attach($question->id, ['position' => 1]);

        ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->create();

        $this->artisan('cpns:seed-bank')
            ->expectsOutputToContain('komposisinya tidak diubah')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('exam_package_question')->where('exam_package_id', $package->id)->count());
    }

    public function test_prune_removes_unmanaged_items_and_keeps_the_ones_already_used(): void
    {
        $orphan = Question::factory()->create();

        $package = ExamPackage::factory()->create();
        $frozen = Question::factory()->create();
        $package->questions()->attach($frozen->id, ['position' => 1]);

        ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->create();

        $this->artisan('cpns:seed-bank --prune --no-package')->assertSuccessful();

        $this->assertNull(Question::query()->find($orphan->id));
        $this->assertNotNull(Question::query()->find($frozen->id));
        $this->assertFalse(Question::query()->find($frozen->id)->is_active);
    }

    public function test_no_package_writes_the_bank_only(): void
    {
        $this->artisan('cpns:seed-bank --no-package')->assertSuccessful();

        $this->assertSame((int) config('cpns.total_items'), Question::query()->count());
        $this->assertSame(0, ExamPackage::query()->count());
    }

    public function test_it_refuses_a_broken_bank_instead_of_writing_half_of_it(): void
    {
        $bank = resource_path('content/questions');
        $original = file_get_contents($bank.'/twk.php');

        try {
            file_put_contents($bank.'/twk.php', str_replace(
                "'key' => '",
                "'key' => 'Z', 'typo' => '",
                $original,
            ));

            $this->artisan('cpns:seed-bank')->assertFailed();

            $this->assertSame(0, Question::query()->count(), 'Bank yang gagal diperiksa tidak boleh menulis apa pun.');
        } finally {
            file_put_contents($bank.'/twk.php', $original);
        }
    }
}
