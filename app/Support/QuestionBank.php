<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\QuestionCategory;
use App\Models\QuestionOption;
use RuntimeException;

/**
 * Reads the CPNS question bank that ships with the app.
 *
 * One file per subtest under resources/content/questions, each returning a list of
 * items. The bank is authored content: versioned with the code, reviewed like code,
 * and written into the database by `cpns:seed-bank`, which is also the documented
 * way an edited bank reaches a server.
 *
 * Two item shapes, matching what the model itself expects:
 *
 *   keyed (TWK, TIU)   'options' => ['A' => 'teks', …], 'key' => 'C'
 *   weighted (TKP)     'options' => ['A' => ['body' => 'teks', 'score' => 5], …]
 *
 * The weighted shape keeps a body beside its own weight, so the two cannot be
 * misaligned, and it is the shape `Question::syncOptions()` already takes — the same
 * one the admin form and the bulk importer build.
 *
 * Every item is checked before a single one is written, and a bank that fails throws
 * with the whole list of problems. A malformed bank is a bug in a file, not data to
 * tolerate: an item that quietly failed validation is an item a participant would
 * meet as a blank or unanswerable question, and that is much more expensive than a
 * refused seed.
 */
final class QuestionBank
{
    /** Shorter than this is a fragment, not a question. */
    private const MIN_STEM_LENGTH = 10;

    /** A one-word "explanation" teaches nothing; this is the floor for a real one. */
    private const MIN_EXPLANATION_LENGTH = 30;

    public function __construct(private readonly string $root) {}

    /**
     * @return list<array{
     *     code: string,
     *     category: QuestionCategory,
     *     subcategory: string,
     *     stem: string,
     *     options: array<string, string>,
     *     key: string|null,
     *     weights: array<string, int>|null,
     *     explanation: string
     * }>
     */
    public function items(): array
    {
        $items = [];
        $errors = [];
        $seen = [];

        foreach ($this->files() as $path) {
            $file = basename($path);
            $category = QuestionCategory::tryFrom(basename($path, '.php'));

            if ($category === null) {
                $errors[] = $file.': nama berkas harus salah satu dari '.$this->categoryNames().'.';

                continue;
            }

            $content = $this->read($path);

            if ($content === null) {
                $errors[] = $file.': berkas harus mengembalikan array berisi butir soal.';

                continue;
            }

            foreach ($content as $index => $item) {
                $code = is_array($item) ? (string) ($item['code'] ?? '') : '';
                $problems = $this->problems($category, $item);

                if ($code !== '' && isset($seen[$code])) {
                    $problems[] = 'kode '.$code.' juga dipakai di '.$seen[$code];
                }

                foreach ($problems as $problem) {
                    $errors[] = $file.' butir '.($index + 1).' ('.$code.'): '.$problem;
                }

                if ($problems !== []) {
                    continue;
                }

                $seen[$code] = $file;
                $items[] = $this->normalise($category, $item);
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(
                "Bank soal tidak lolos pemeriksaan:\n - ".implode("\n - ", $errors),
            );
        }

        return $items;
    }

    /**
     * @param  list<array{category: QuestionCategory, ...}>  $items
     * @return array<string, int> subtest label => item count
     */
    public static function counts(array $items): array
    {
        $counts = [];

        foreach (QuestionCategory::cases() as $category) {
            $counts[$category->label()] = count(
                array_filter($items, static fn (array $item): bool => $item['category'] === $category),
            );
        }

        return $counts;
    }

    /**
     * The subtests in blueprint order. The package is assembled in this order, so a
     * sitting always reads TWK, then TIU, then TKP — the order of the real exam, not
     * the alphabetical order of the file names.
     *
     * @return list<QuestionCategory>
     */
    public static function order(): array
    {
        return QuestionCategory::cases();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     code: string,
     *     category: QuestionCategory,
     *     subcategory: string,
     *     stem: string,
     *     options: array<string, string>,
     *     key: string|null,
     *     weights: array<string, int>|null,
     *     explanation: string
     * }
     */
    private function normalise(QuestionCategory $category, array $item): array
    {
        $bodies = [];
        $weights = null;

        /** @var array<string, mixed> $options */
        $options = $item['options'];

        foreach ($options as $letter => $value) {
            if (is_array($value)) {
                $bodies[$letter] = (string) $value['body'];
                $weights[$letter] = (int) $value['score'];

                continue;
            }

            $bodies[$letter] = (string) $value;
        }

        return [
            'code' => (string) $item['code'],
            'category' => $category,
            'subcategory' => (string) $item['subcategory'],
            'stem' => (string) $item['stem'],
            'options' => $bodies,
            'key' => $category->requiresKey() ? (string) $item['key'] : null,
            'weights' => $category->requiresKey() ? null : $weights,
            'explanation' => (string) $item['explanation'],
        ];
    }

    /** @return list<string> */
    private function files(): array
    {
        $paths = glob($this->root.'/*.php') ?: [];
        sort($paths);

        return array_values($paths);
    }

    /**
     * @return list<mixed>|null
     */
    private function read(string $path): ?array
    {
        $content = require $path;

        if (! is_array($content)) {
            return null;
        }

        return array_values($content);
    }

    /**
     * Everything wrong with one item, so one seed reports every problem at once
     * instead of one per run.
     *
     * @return list<string>
     */
    private function problems(QuestionCategory $category, mixed $item): array
    {
        if (! is_array($item)) {
            return ['butir harus berupa array.'];
        }

        $problems = [];
        $code = (string) ($item['code'] ?? '');

        if (preg_match('/^[A-Z]{3}-\d{3}$/', $code) !== 1) {
            $problems[] = 'kode harus berbentuk seperti TWK-001.';
        }

        $subcategory = (string) ($item['subcategory'] ?? '');

        if (! array_key_exists($subcategory, $category->subcategories())) {
            $problems[] = 'submateri ['.$subcategory.'] tidak ada di kisi-kisi '.$category->label().'.';
        }

        if (mb_strlen((string) ($item['stem'] ?? '')) < self::MIN_STEM_LENGTH) {
            $problems[] = 'teks soal terlalu pendek.';
        }

        if (mb_strlen(trim((string) ($item['explanation'] ?? ''))) < self::MIN_EXPLANATION_LENGTH) {
            $problems[] = 'pembahasan belum diisi.';
        }

        $options = $item['options'] ?? null;

        if (! is_array($options) || array_keys($options) !== QuestionOption::LETTERS) {
            return [...$problems, 'opsi harus tepat lima, berlabel A sampai E dan berurutan.'];
        }

        return [...$problems, ...$this->optionProblems($category, $options), ...$this->gradingProblems($category, $item, $options)];
    }

    /**
     * The five option bodies — and, for TKP, the weight each one carries.
     *
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function optionProblems(QuestionCategory $category, array $options): array
    {
        $problems = [];

        foreach ($options as $letter => $value) {
            if ($category->requiresKey()) {
                if (! is_string($value) || trim($value) === '') {
                    $problems[] = 'opsi '.$letter.' masih kosong.';
                }

                continue;
            }

            if (! is_array($value)) {
                $problems[] = 'opsi '.$letter.' harus berisi teks tindakan dan bobotnya.';

                continue;
            }

            if (! is_string($value['body'] ?? null) || trim((string) ($value['body'] ?? '')) === '') {
                $problems[] = 'opsi '.$letter.' masih kosong.';
            }

            if (! is_int($value['score'] ?? null)) {
                $problems[] = 'bobot opsi '.$letter.' harus bilangan bulat.';
            }
        }

        return $problems;
    }

    /**
     * The grading shape of one item: a key for TWK and TIU, a full weight ladder for
     * TKP. Both rules exist once, here, and they mirror the model — an item is either
     * keyed or weighted, never both and never neither.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function gradingProblems(QuestionCategory $category, array $item, array $options): array
    {
        if ($category->requiresKey()) {
            $problems = [];

            if (array_key_exists('weights', $item)) {
                $problems[] = 'butir berkunci tidak boleh punya bobot.';
            }

            if (! in_array((string) ($item['key'] ?? ''), QuestionOption::LETTERS, true)) {
                $problems[] = 'kunci harus salah satu dari '.implode(', ', QuestionOption::LETTERS).'.';
            }

            return $problems;
        }

        if (array_key_exists('key', $item)) {
            return ['butir TKP tidak punya kunci karena semua opsinya dinilai.'];
        }

        $scores = [];

        foreach ($options as $value) {
            if (is_array($value) && is_int($value['score'] ?? null)) {
                $scores[] = $value['score'];
            }
        }

        sort($scores);
        $ladder = range(1, $category->perItemMaximum());

        if ($scores !== $ladder) {
            return ['bobot TKP harus tepat '.implode(', ', $ladder).' tanpa bobot kembar.'];
        }

        return [];
    }

    private function categoryNames(): string
    {
        return implode(', ', array_map(
            static fn (QuestionCategory $category): string => $category->value,
            QuestionCategory::cases(),
        ));
    }
}
