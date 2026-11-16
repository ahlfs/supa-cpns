<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuestionCategory;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Support\PackageComposition;
use App\Support\QuestionBank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes the shipped question bank into the database, and builds the demo tryout
 * from it.
 *
 * This is the third writer of bank items, next to the admin form and the bulk
 * importer, and the only one that can be run again safely: every item carries a
 * code, so re-running updates the item it wrote last time instead of appending a
 * second copy. That makes `cpns:seed-bank` the way an edited bank file reaches a
 * server: run it by hand after merging a bank change.
 *
 * Deliberately NOT run by deploy/host-cpns.sh. A deploy that re-seeded would quietly
 * overwrite the edits an administrator made to these same items in the panel, and
 * that trade — code-managed content versus live edits — is the operator's call, not
 * a side effect of publishing.
 */
final class SeedQuestionBankCommand extends Command
{
    protected $signature = 'cpns:seed-bank
        {--package=Tryout SKD #1 : Judul paket contoh yang dibuat atau diperbarui}
        {--slug=tryout-skd-1 : Slug paket contoh}
        {--no-package : Hanya menulis bank soal}
        {--prune : Bersihkan soal yang tidak ada di bank}';

    protected $description = 'Write the CPNS question bank from resources/content into the database';

    public function handle(QuestionBank $bank): int
    {
        try {
            $items = $bank->items();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                $this->write($item);
            }
        });

        $this->info('Bank soal: '.count($items).' butir ditulis.');

        foreach (QuestionBank::counts($items) as $label => $count) {
            $this->line('  '.$label.': '.$count.' butir');
        }

        if ($this->option('prune')) {
            $this->prune($items);
        }

        if (! $this->option('no-package')) {
            $this->buildPackage($items);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function write(array $item): void
    {
        $question = Question::query()->updateOrCreate(
            ['code' => $item['code']],
            [
                'category' => $item['category'],
                'subcategory' => $item['subcategory'],
                'stem' => $item['stem'],
                'explanation' => $item['explanation'],
                'is_active' => true,
            ],
        );

        $options = [];

        foreach ($item['options'] as $letter => $body) {
            $options[$letter] = $item['weights'] === null
                ? ['body' => $body]
                : ['body' => $body, 'score' => $item['weights'][$letter]];
        }

        // One home for the grading shape: the same call the form and the importer make.
        $question->syncOptions($options, $item['key']);
    }

    /**
     * @param  list<array{code: string, category: QuestionCategory, ...}>  $items
     */
    private function buildPackage(array $items): void
    {
        $title = (string) $this->option('package');
        $slug = (string) $this->option('slug');

        $package = ExamPackage::query()->firstOrNew(['slug' => $slug]);

        if (! $package->exists) {
            $package->fill([
                'title' => $title,
                'description' => 'Paket tryout SKD lengkap: 30 soal TWK, 35 soal TIU, dan 45 soal TKP.',
                'duration_minutes' => (int) config('cpns.duration_minutes'),
                'status' => ExamPackage::STATUS_DRAFT,
            ])->save();

            $this->info("Paket [{$title}] dibuat.");
        }

        if ($package->isLocked()) {
            // Composition is frozen once anybody has sat the package, so two sittings
            // stay comparable. The items themselves remain editable in the bank.
            $this->warn("Paket [{$package->title}] sudah punya percobaan peserta, jadi komposisinya tidak diubah.");
        } else {
            $this->compose($package, $items);
        }

        $package->status = PackageComposition::isComplete($package)
            ? ExamPackage::STATUS_PUBLISHED
            : ExamPackage::STATUS_DRAFT;
        $package->save();

        foreach (PackageComposition::report($package) as $row) {
            $this->line(sprintf(
                '  %s: %d dari %d%s',
                $row['label'],
                $row['present'],
                $row['required'],
                $row['complete'] ? '' : ' — belum lengkap',
            ));
        }

        if ($package->isPublished()) {
            $this->info("Paket [{$package->title}] terbit dan bisa dikerjakan peserta.");
        } else {
            $this->warn('Paket belum lengkap: '.PackageComposition::shortfallMessage($package));
        }
    }

    /**
     * Replaces the composition with exactly the bank: the blueprint order, each
     * subtest in code order, and positions numbered without gaps because the pivot
     * carries a unique index on them.
     *
     * @param  list<array{code: string, category: QuestionCategory, ...}>  $items
     */
    private function compose(ExamPackage $package, array $items): void
    {
        $codes = array_column($items, 'code');

        /** @var array<string, Question> $questions */
        $questions = Question::query()->whereIn('code', $codes)->get()->keyBy('code');

        $package->questions()->detach();

        $position = 1;

        foreach (QuestionBank::order() as $category) {
            foreach ($items as $item) {
                if ($item['category'] !== $category || ! isset($questions[$item['code']])) {
                    continue;
                }

                $package->questions()->attach($questions[$item['code']]->id, ['position' => $position]);
                $position++;
            }
        }
    }

    /**
     * Removes bank items the shipped bank no longer contains.
     *
     * Deleting is only safe while nothing depends on the item. A question that has
     * been answered, or that sits in a package somebody has already sat, is
     * deactivated instead: the pivot's foreign key refuses the delete anyway, and
     * the history of a finished attempt must not shift under the participant who
     * earned it.
     *
     * @param  list<array{code: string, ...}>  $items
     */
    private function prune(array $items): void
    {
        $codes = array_column($items, 'code');

        $stale = Question::query()
            ->where(static fn ($query) => $query->whereNull('code')->orWhereNotIn('code', $codes))
            ->get();

        if ($stale->isEmpty()) {
            $this->line('Sapu: tidak ada soal di luar bank.');

            return;
        }

        $answered = ExamAttemptAnswer::query()
            ->whereIn('question_id', $stale->modelKeys())
            ->pluck('question_id')
            ->unique()
            ->all();

        $lockedPackages = ExamPackage::query()->has('attempts')->pluck('id')->all();

        $deleted = 0;
        $kept = 0;

        foreach ($stale as $question) {
            $inLockedPackage = $question->packages()
                ->whereIn('exam_packages.id', $lockedPackages)
                ->exists();

            if (in_array($question->id, $answered, true) || $inLockedPackage) {
                $question->forceFill(['is_active' => false])->save();
                $kept++;

                continue;
            }

            $question->packages()->detach();
            $question->delete();
            $deleted++;
        }

        $this->info("Sapu: {$deleted} soal dihapus, {$kept} soal dinonaktifkan karena sudah dipakai.");
    }
}
