<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\QuestionCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Support\QuestionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class ImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,key,explanation';

    private const TKP_HEADER = 'category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,score_a,score_b,score_c,score_d,score_e';

    private function twkRow(string $stem = 'Soal impor pertama yang cukup panjang'): string
    {
        return "twk,pilar_negara,\"{$stem}\",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,B,\"Pembahasan singkat.\"";
    }

    private function tkpRow(string $scores = '1,2,3,4,5'): string
    {
        return 'tkp,pelayanan_publik,"Soal TKP impor yang cukup panjang",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,'.$scores;
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_clean_block_is_imported(): void
    {
        $payload = self::HEADER."\n".$this->twkRow()."\n".$this->tkpRow();

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertRedirect(route('admin.import.index'))
            ->assertSessionHas('status');

        $this->assertSame(2, Question::query()->count());
        $this->assertSame(1, Question::query()->category(QuestionCategory::Tkp)->count());
        $this->assertSame(
            [1, 2, 3, 4, 5],
            Question::query()->category(QuestionCategory::Tkp)->first()->options->pluck('score')->sort()->values()->all(),
        );
    }

    public function test_a_pasted_tab_separated_block_works_too(): void
    {
        $payload = str_replace(',', "\t", self::HEADER)."\n".
            "twk\tpilar_negara\tSoal dengan pemisah tab dan cukup panjang\tOpsi A\tOpsi B\tOpsi C\tOpsi D\tOpsi E\tA\tPembahasan.";

        $this->actingAs($this->admin())->post('/admin/impor', ['payload' => $payload])->assertRedirect();

        $this->assertSame(1, Question::query()->count());
        $this->assertSame('A', Question::query()->firstOrFail()->correctOption()->label);
    }

    public function test_one_bad_row_writes_nothing_and_names_its_line(): void
    {
        $payload = self::HEADER."\n".$this->twkRow()."\n".'twk,entah_apa,"Soal dengan submateri salah",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,B,';

        $response = $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $payload]);

        $response->assertOk();
        $response->assertSee('Baris 3');

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertSessionHasErrors('payload');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_tkp_row_with_a_repeated_weight_is_reported_by_line(): void
    {
        $payload = self::HEADER."\n".$this->tkpRow('5,5,3,2,1');

        $response = $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $payload]);

        $response->assertSee('Baris 2');
        $response->assertSee('bobot kembar', escape: false);

        $this->assertSame(0, Question::query()->count());
    }

    /**
     * A TKP row whose stem carries an unquoted comma is one cell wider than the 13
     * cells a TKP row accounts for. The tail read used to accept it anyway, so the
     * option bodies came from the leading cells one place to the left and the weights
     * from the tail: an item with shifted bodies and a cut stem, imported silently.
     */
    public function test_a_row_wider_than_its_shape_allows_is_refused_by_line(): void
    {
        $row = 'tkp,pelayanan_publik,Seorang warga mengeluh keras di loketmu, sikapmu adalah'
            .',Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,1,2,3,4,5';

        $payload = self::HEADER."\n".$row;

        $response = $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $payload]);

        $response->assertOk();
        $response->assertSee('Baris 2');
        // The reason names the actual count and both the widths it could have been.
        $response->assertSee('14 kolom', escape: false);
        $response->assertSee('10 atau 13', escape: false);

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertSessionHasErrors('payload');

        $this->assertSame(0, Question::query()->count());
    }

    /**
     * The documented mixed block: a TKP row under the TWK header. Its count is
     * exactly 8 shared cells plus its own 5 weights, so the exact-width rule lets it
     * through and it keeps its own bodies, weights and full stem.
     */
    public function test_a_tkp_row_under_a_twk_header_keeps_its_own_cells(): void
    {
        $payload = self::HEADER."\n".$this->tkpRow();

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertRedirect(route('admin.import.index'))
            ->assertSessionHas('status');

        $question = Question::query()->firstOrFail();

        $this->assertSame('Soal TKP impor yang cukup panjang', $question->stem);
        $this->assertSame(
            ['A' => 'Opsi A', 'B' => 'Opsi B', 'C' => 'Opsi C', 'D' => 'Opsi D', 'E' => 'Opsi E'],
            $question->options()->orderBy('label')->pluck('body', 'label')->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5], $question->options()->orderBy('label')->pluck('score')->all());
    }

    /**
     * A keyed row that leaves off its optional explanation is one cell short of its
     * own 10 while the header names the weighted 13. It must be refused for the
     * missing cell — the real cause — and not misreported as a bad key column.
     */
    public function test_a_keyed_row_missing_its_explanation_cell_is_refused_for_its_cell_count(): void
    {
        $row = 'twk,pilar_negara,"Soal TWK tanpa sel pembahasan",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,B';
        $payload = self::TKP_HEADER."\n".$row;

        $response = $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $payload]);

        $response->assertOk();
        $response->assertSee('Baris 2');
        $response->assertSee('9 kolom', escape: false);
        $response->assertSee('10 atau 13', escape: false);
        $response->assertDontSee('Kolom key harus', escape: false);

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertSessionHasErrors('payload');

        $this->assertSame(0, Question::query()->count());
    }

    /**
     * The other half of the documented mixed block: a keyed row under the TKP header.
     * It is 10 cells against a 13-cell header, so record() pads it — and its key and
     * explanation still come from its own tail, not from the header's score columns.
     */
    public function test_a_keyed_row_under_a_tkp_header_keeps_its_own_key_and_explanation(): void
    {
        $row = 'twk,pilar_negara,"Soal TWK di blok TKP yang cukup panjang",'
            .'Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,C,"Pembahasan singkat."';

        $payload = self::TKP_HEADER."\n".$row;

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertRedirect(route('admin.import.index'))
            ->assertSessionHas('status');

        $question = Question::query()->firstOrFail();

        $this->assertSame('C', $question->correctOption()->label);
        $this->assertSame('Pembahasan singkat.', $question->explanation);
        $this->assertSame(0, $question->options()->whereNotNull('score')->count());
    }

    /**
     * The worked examples the format document ships, parsed by the shipped parser: a
     * documented input is the one thing a width rule must never break, and a doc whose
     * example stops importing is worse than no doc.
     */
    public function test_the_documented_examples_parse_with_no_errors(): void
    {
        /** @var string $document */
        $document = file_get_contents(base_path('docs/QUESTION-IMPORT-FORMAT.md'));

        preg_match_all('/```csv\s*\n(.*?)```/s', $document, $matches);

        $this->assertCount(3, $matches[1], 'The document ships a TWK/TIU example, a TKP one, and the empty-explanation one.');

        $parser = new QuestionParser;

        $counts = [];

        foreach ($matches[1] as $block) {
            $parsed = $parser->parse($block);

            $this->assertSame([], $parsed['errors'], 'Every documented example must parse clean.');

            $counts[] = count($parsed['rows']);
        }

        $this->assertSame([2, 1, 1], $counts, 'Two TWK/TIU rows, one TKP row, and the empty-explanation row, as printed.');
    }

    public function test_an_empty_payload_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => ''])
            ->assertSessionHasErrors('payload');
    }

    public function test_a_peserta_cannot_import(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/impor', ['payload' => self::HEADER."\n".$this->twkRow()])
            ->assertForbidden();
    }

    public function test_an_uploaded_csv_is_used_and_a_bad_file_alone_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => ''])
            ->assertSessionHasErrors('payload');

        $this->actingAs($this->admin())->post('/admin/impor', [
            'file' => UploadedFile::fake()->createWithContent('soal.csv', self::HEADER."\n".$this->twkRow()),
        ])->assertRedirect(route('admin.import.index'));

        $this->assertSame(1, Question::query()->count());

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['file' => UploadedFile::fake()->create('soal.pdf', 4, 'application/pdf')])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, Question::query()->count());
    }

    public function test_the_impor_menu_entry_is_live_in_the_admin_sidebar(): void
    {
        // The layout drops any nav entry whose route name does not exist, so a typo
        // in routes/admin.php would silently hide the screen instead of failing.
        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSee('Impor soal');
        $response->assertSee('href="'.route('admin.import.index').'"', escape: false);
    }

    public function test_the_import_button_stays_disabled_while_any_row_is_broken(): void
    {
        $dirty = self::HEADER."\n".$this->twkRow()."\n".'twk,entah_apa,"Soal dengan submateri salah",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,B,';
        $clean = self::HEADER."\n".$this->twkRow();

        $this->assertTrue(
            $this->commitButtonIsDisabled(
                $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $dirty])->getContent(),
            ),
            'One broken row must leave the import button disabled.',
        );

        $this->assertFalse(
            $this->commitButtonIsDisabled(
                $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $clean])->getContent(),
            ),
            'A clean preview must enable the import button.',
        );
    }

    /**
     * The grading shape — one key and no weights, or five weights and no key — is
     * derived in one place now, and both writers call it. This is the test that says
     * so: the same item typed into the form and pasted into the importer must leave
     * byte-identical option rows, so a future change made in one writer only cannot
     * pass unnoticed.
     */
    public function test_the_form_and_the_importer_write_the_same_keyed_item(): void
    {
        $stem = 'Soal TWK dari dua penulis yang cukup panjang';
        $bodies = $this->bodies();

        $this->actingAs($this->admin())->post('/admin/soal', [
            'category' => 'twk',
            'subcategory' => 'pilar_negara',
            'stem' => $stem,
            'explanation' => 'Pembahasan.',
            'is_active' => '1',
            'key' => 'C',
            'options' => $this->formOptions($bodies),
        ])->assertRedirect();

        $this->actingAs($this->admin())->post('/admin/impor', ['payload' => self::HEADER."\n".implode(',', [
            'twk',
            'pilar_negara',
            '"'.$stem.'"',
            ...$bodies,
            'C',
            '"Pembahasan."',
        ])])->assertRedirect();

        [$viaForm, $viaImport] = $this->pairOf($stem);

        $this->assertSame($this->optionRows($viaForm), $this->optionRows($viaImport));
        $this->assertSame(1, $viaImport->options()->where('is_correct', true)->count());
        $this->assertSame('C', $viaImport->correctOption()->label);
        $this->assertSame(0, $viaImport->options()->whereNotNull('score')->count());
    }

    public function test_the_form_and_the_importer_write_the_same_weighted_item(): void
    {
        $stem = 'Soal TKP dari dua penulis yang cukup panjang';
        $bodies = $this->bodies();
        $scores = [5, 4, 3, 2, 1];

        $this->actingAs($this->admin())->post('/admin/soal', [
            'category' => 'tkp',
            'subcategory' => 'pelayanan_publik',
            'stem' => $stem,
            'explanation' => 'Pembahasan.',
            'is_active' => '1',
            'options' => $this->formOptions($bodies, $scores),
        ])->assertRedirect();

        $this->actingAs($this->admin())->post('/admin/impor', ['payload' => self::TKP_HEADER."\n".implode(',', [
            'tkp',
            'pelayanan_publik',
            '"'.$stem.'"',
            ...$bodies,
            ...$scores,
        ])])->assertRedirect();

        [$viaForm, $viaImport] = $this->pairOf($stem);

        $imported = $this->optionRows($viaImport);

        $this->assertSame($this->optionRows($viaForm), $imported);
        $this->assertSame(0, $viaImport->options()->whereNotNull('is_correct')->count());
        $this->assertSame([5, 4, 3, 2, 1], array_column($imported, 'score'));
    }

    /**
     * @return array<string, string> label => body
     */
    private function bodies(): array
    {
        return [
            'A' => 'Opsi A',
            'B' => 'Opsi B',
            'C' => 'Opsi C',
            'D' => 'Opsi D',
            'E' => 'Opsi E',
        ];
    }

    /**
     * @param  array<string, string>  $bodies
     * @param  list<int>  $scores
     * @return array<string, array<string, mixed>>
     */
    private function formOptions(array $bodies, array $scores = []): array
    {
        $options = [];

        foreach (array_values(QuestionOption::LETTERS) as $index => $letter) {
            $options[$letter] = ['body' => $bodies[$letter]];

            if ($scores !== []) {
                $options[$letter]['score'] = $scores[$index];
            }
        }

        return $options;
    }

    /**
     * The two questions carrying $stem, in the order they were written: the form's
     * first, then the importer's.
     *
     * @return array{0: Question, 1: Question}
     */
    private function pairOf(string $stem): array
    {
        $questions = Question::query()->where('stem', $stem)->orderBy('id')->get()->all();

        $this->assertCount(2, $questions, 'Both writers must have produced the item.');

        return [$questions[0], $questions[1]];
    }

    /**
     * @return array<string, array{is_correct: bool|null, score: int|null}>
     */
    private function optionRows(Question $question): array
    {
        return $question->options()
            ->get()
            ->mapWithKeys(fn (QuestionOption $option): array => [
                $option->label => ['is_correct' => $option->is_correct, 'score' => $option->score],
            ])
            ->all();
    }

    private function commitButtonIsDisabled(string $html): bool
    {
        $pattern = '#<form[^>]*action="'.preg_quote(route('admin.import.commit'), '#').'"[^>]*>(.*?)</form>#s';

        $this->assertMatchesRegularExpression($pattern, $html, 'The preview must offer the commit form.');

        preg_match($pattern, $html, $form);
        preg_match('/<button\b[^>]*>/i', $form[1], $button);

        // The button's class list carries `disabled:opacity-40`, so the check reads
        // the attribute itself — a bare `disabled` followed by a space or the tag's
        // own end — and never the word inside the class.
        return (bool) preg_match('/\sdisabled(?=[\s>\/])/i', $button[0] ?? '');
    }
}
