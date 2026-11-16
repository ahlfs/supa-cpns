<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\QuestionCategory;
use App\Support\QuestionBank;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * The loader is the gate the authored bank passes through before anything is
 * written, so each refusal below is checked by actually feeding it a broken file.
 */
final class QuestionBankLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/bank-'.uniqid());
        File::makeDirectory($this->root, 0o755, true, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_reads_a_sound_item_with_its_key(): void
    {
        $this->file('twk.php', [$this->keyed('TWK-001')]);

        $items = $this->bank()->items();

        $this->assertCount(1, $items);
        $this->assertSame('TWK-001', $items[0]['code']);
        $this->assertSame(QuestionCategory::Twk, $items[0]['category']);
        $this->assertSame('C', $items[0]['key']);
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_keys($items[0]['options']));
        $this->assertNull($items[0]['weights']);
    }

    public function test_it_reads_a_weighted_item_with_its_ladder(): void
    {
        $this->file('tkp.php', [$this->weighted('TKP-001')]);

        $items = $this->bank()->items();

        $this->assertCount(1, $items);
        $this->assertNull($items[0]['key']);
        $this->assertSame(['A' => 'tindakan terbaik', 'B' => 'tindakan baik'], array_slice($items[0]['options'], 0, 2, true));
        $this->assertSame(['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'E' => 1], $items[0]['weights']);
    }

    public function test_it_refuses_a_keyed_item_with_a_duplicate_weight(): void
    {
        $item = $this->keyed('TWK-001');
        $item['weights'] = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'E' => 1];

        $this->assertRefused([$item], 'butir berkunci tidak boleh punya bobot');
    }

    public function test_it_refuses_a_weighted_item_with_a_broken_ladder(): void
    {
        $item = $this->weighted('TKP-001');
        $item['options']['E']['score'] = 4;

        $this->assertRefused([$item], 'bobot TKP harus tepat 1, 2, 3, 4, 5 tanpa bobot kembar', 'tkp.php');
    }

    public function test_it_refuses_a_keyed_item_whose_key_is_not_an_option_letter(): void
    {
        $item = $this->keyed('TWK-001');
        $item['key'] = 'F';

        $this->assertRefused([$item], 'kunci harus salah satu dari A, B, C, D, E');
    }

    public function test_it_refuses_an_unknown_subcategory(): void
    {
        $item = $this->keyed('TWK-001');
        $item['subcategory'] = 'materi_karangan';

        $this->assertRefused([$item], 'tidak ada di kisi-kisi TWK');
    }

    public function test_it_refuses_a_duplicate_code(): void
    {
        $this->assertRefused([$this->keyed('TWK-001'), $this->keyed('TWK-001')], 'kode TWK-001 juga dipakai');
    }

    public function test_it_refuses_an_item_with_a_missing_option(): void
    {
        $item = $this->keyed('TWK-001');
        unset($item['options']['D']);

        $this->assertRefused([$item], 'opsi harus tepat lima');
    }

    public function test_it_refuses_an_item_without_an_explanation(): void
    {
        $item = $this->keyed('TWK-001');
        $item['explanation'] = 'Ada.';

        $this->assertRefused([$item], 'pembahasan belum diisi');
    }

    /**
     * Every broken item must be named in one report: fixing a bank one error per run
     * is how an evening disappears.
     */
    public function test_it_reports_every_problem_at_once(): void
    {
        try {
            $this->file('twk.php', [
                ['code' => 'salah', 'subcategory' => 'entah', 'stem' => 'x', 'options' => [], 'key' => 'Z', 'explanation' => 'y'],
            ]);

            $this->bank()->items();
            $this->fail('A broken bank must throw.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('kode harus berbentuk', $exception->getMessage());
            $this->assertStringContainsString('teks soal terlalu pendek', $exception->getMessage());
            $this->assertStringContainsString('opsi harus tepat lima', $exception->getMessage());
        }
    }

    public function test_it_refuses_a_file_that_is_not_a_subtest_name(): void
    {
        $this->file('psikotes.php', [$this->keyed('TWK-001')]);

        try {
            $this->bank()->items();
            $this->fail('An unknown file must throw.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nama berkas harus salah satu dari twk, tiu, tkp', $exception->getMessage());
        }
    }

    /**
     * @param  list<mixed>  $items
     */
    private function assertRefused(array $items, string $fragment, string $file = 'twk.php'): void
    {
        $this->file($file, $items);

        try {
            $this->bank()->items();
            $this->fail("The loader accepted a bank that should have been refused for: {$fragment}");
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($fragment, $exception->getMessage());
        }
    }

    /**
     * @param  list<mixed>  $items
     */
    private function file(string $name, array $items): void
    {
        File::put($this->root.'/'.$name, '<?php return '.var_export($items, true).';');
    }

    /**
     * @return array<string, mixed>
     */
    private function keyed(string $code): array
    {
        return [
            'code' => $code,
            'subcategory' => 'pilar_negara',
            'stem' => 'Pernyataan yang benar mengenai lembaga negara adalah ...',
            'options' => [
                'A' => 'opsi pertama',
                'B' => 'opsi kedua',
                'C' => 'opsi ketiga',
                'D' => 'opsi keempat',
                'E' => 'opsi kelima',
            ],
            'key' => 'C',
            'explanation' => 'Penjelasan yang cukup panjang supaya lolos ambang pembahasan.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function weighted(string $code): array
    {
        return [
            'code' => $code,
            'subcategory' => 'pelayanan_publik',
            'stem' => 'Seorang warga meminta layanan di luar prosedur yang berlaku.',
            'options' => [
                'A' => ['body' => 'tindakan terbaik', 'score' => 5],
                'B' => ['body' => 'tindakan baik', 'score' => 4],
                'C' => ['body' => 'tindakan cukup', 'score' => 3],
                'D' => ['body' => 'tindakan kurang', 'score' => 2],
                'E' => ['body' => 'tindakan terburuk', 'score' => 1],
            ],
            'explanation' => 'Penjelasan yang cukup panjang supaya lolos ambang pembahasan.',
        ];
    }

    private function bank(): QuestionBank
    {
        return new QuestionBank($this->root);
    }
}
