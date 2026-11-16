<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuestionAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function twkPayload(array $overrides = []): array
    {
        return $overrides + [
            'category' => 'twk',
            'subcategory' => 'pilar_negara',
            'stem' => 'Pancasila sebagai dasar negara mengandung konsekuensi bahwa',
            'explanation' => 'Karena Pancasila menjadi sumber dari segala sumber hukum.',
            'is_active' => '1',
            'key' => 'C',
            'options' => [
                'A' => ['body' => 'Opsi A'],
                'B' => ['body' => 'Opsi B'],
                'C' => ['body' => 'Opsi C'],
                'D' => ['body' => 'Opsi D'],
                'E' => ['body' => 'Opsi E'],
            ],
        ];
    }

    private function tkpPayload(array $scores = [5, 4, 3, 2, 1]): array
    {
        return [
            'category' => 'tkp',
            'subcategory' => 'pelayanan_publik',
            'stem' => 'Seorang warga mengeluh di loket tempatmu bertugas, sikapmu adalah',
            'explanation' => 'Yang paling selaras dengan nilai pelayanan publik.',
            'is_active' => '1',
            'options' => [
                'A' => ['body' => 'Opsi A', 'score' => $scores[0]],
                'B' => ['body' => 'Opsi B', 'score' => $scores[1]],
                'C' => ['body' => 'Opsi C', 'score' => $scores[2]],
                'D' => ['body' => 'Opsi D', 'score' => $scores[3]],
                'E' => ['body' => 'Opsi E', 'score' => $scores[4]],
            ],
        ];
    }

    public function test_a_peserta_cannot_reach_the_bank(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/soal')->assertForbidden();
    }

    public function test_the_bank_soal_menu_entry_is_live_in_the_admin_sidebar(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSee('Bank soal');
        $response->assertSee('href="'.route('admin.questions.index').'"', escape: false);
    }

    public function test_an_admin_creates_a_keyed_item_with_exactly_one_key(): void
    {
        $this->actingAs($this->admin())->post('/admin/soal', $this->twkPayload())
            ->assertRedirect(route('admin.questions.index', ['category' => 'twk']));

        $question = Question::query()->firstOrFail();

        $this->assertSame(QuestionCategory::Twk, $question->category);
        $this->assertSame(5, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertSame('C', $question->correctOption()->label);
        $this->assertSame(0, $question->options()->whereNotNull('score')->count());
    }

    public function test_a_tkp_item_saves_with_a_full_weight_ladder(): void
    {
        $this->actingAs($this->admin())->post('/admin/soal', $this->tkpPayload())->assertRedirect();

        $question = Question::query()->firstOrFail();

        $this->assertSame([1, 2, 3, 4, 5], $question->options->pluck('score')->sort()->values()->all());
        $this->assertSame(0, $question->options()->whereNotNull('is_correct')->count());
    }

    public function test_a_tkp_item_with_a_repeated_weight_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/soal', $this->tkpPayload([5, 5, 3, 2, 1]))
            ->assertSessionHasErrors('options');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_keyed_item_carrying_weights_is_refused(): void
    {
        $payload = $this->twkPayload();
        $payload['options']['A']['score'] = 3;

        $this->actingAs($this->admin())->post('/admin/soal', $payload)->assertSessionHasErrors('options.A.score');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_tkp_item_carrying_a_key_is_refused(): void
    {
        $payload = $this->tkpPayload();
        $payload['key'] = 'A';

        $this->actingAs($this->admin())->post('/admin/soal', $payload)->assertSessionHasErrors('key');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_subcategory_outside_the_kisi_kisi_is_refused(): void
    {
        $payload = $this->twkPayload();
        $payload['subcategory'] = 'entah_apa';

        $this->actingAs($this->admin())->post('/admin/soal', $payload)->assertSessionHasErrors('subcategory');
    }

    public function test_editing_an_item_in_a_package_that_has_been_sat_still_works(): void
    {
        $package = ExamPackage::factory()->complete()->published()->create();
        $question = $package->questions()->first();
        $attempt = ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->submitted()
            // 200, written the way the grader writes it: one total that is the sum
            // of its parts, not a fifth number that can disagree with them.
            ->withScores(60, 80, 60)
            ->create();

        $payload = $this->twkPayload([
            'category' => 'twk',
            'subcategory' => $question->subcategory,
            'stem' => 'Teks soal yang sudah diperbaiki dan lebih panjang dari sebelumnya',
            'key' => $question->correctOption()->label,
        ]);

        $this->actingAs($this->admin())
            ->put('/admin/soal/'.$question->id, $payload)
            ->assertRedirect();

        $this->assertSame('Teks soal yang sudah diperbaiki dan lebih panjang dari sebelumnya', $question->refresh()->stem);
        $this->assertSame(200, $attempt->refresh()->total_score, 'The frozen score must not move.');
    }

    /**
     * The category is the item's grading shape, not a field on a form: TWK/TIU
     * grade on a single key, TKP on the weight ladder. UpdateQuestionRequest
     * inherits a required `category` rule and `payload()` reads it back, so
     * without this refusal a crafted PUT with `category=twk` turns a TKP item
     * into a TWK item on a package participants are already sitting — the write
     * lands, `syncOptions` re-grades it as keyed, and the composition silently
     * reads 31 TWK / 44 TKP while the sitting keeps serving.
     *
     * The edit form's hidden field is not a boundary, so this is refused on the
     * update path itself. Everything else about the item stays editable.
     */
    public function test_a_crafted_category_change_is_refused_and_the_row_is_untouched(): void
    {
        $package = ExamPackage::factory()->complete()->published()->create();
        $question = $package->questions()->category(QuestionCategory::Tkp)->first();

        ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->submitted()
            ->create();

        $stored = $question->only(['category', 'subcategory', 'stem', 'explanation']);
        $options = $question->options()->orderBy('label')->get()
            ->map(fn (QuestionOption $option): array => [$option->label, $option->is_correct, $option->score])
            ->all();

        $this->actingAs($this->admin())
            ->put('/admin/soal/'.$question->id, $this->twkPayload([
                'stem' => 'Soal TKP yang dicoba dipindahkan ke subtes lain oleh admin',
                'subcategory' => 'pilar_negara',
                'key' => 'C',
            ]))
            ->assertSessionHasErrors('category');

        $question->refresh();

        // The write never landed, so the row and its options are exactly as they
        // were — a refusal that still wrote is the failure this test exists for.
        $this->assertSame($stored, $question->only(['category', 'subcategory', 'stem', 'explanation']));
        $this->assertSame(
            $options,
            $question->options()->orderBy('label')->get()
                ->map(fn (QuestionOption $option): array => [$option->label, $option->is_correct, $option->score])
                ->all(),
        );

        // And the package the participants are sitting still reads its own shape.
        $this->assertSame(QuestionCategory::Twk->itemCount(), $package->questions()->category(QuestionCategory::Twk)->count());
        $this->assertSame(QuestionCategory::Tkp->itemCount(), $package->questions()->category(QuestionCategory::Tkp)->count());
    }

    /**
     * The other half of that boundary: keeping the category must not cost the
     * administrator the edit. A weighted item on a locked package is re-saved with
     * a new stem and a new weight ladder.
     */
    public function test_editing_every_other_field_still_works_with_the_category_unchanged(): void
    {
        $package = ExamPackage::factory()->complete()->published()->create();
        $question = $package->questions()->category(QuestionCategory::Tkp)->first();

        ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->submitted()
            ->create();

        $payload = $this->tkpPayload([4, 3, 2, 1, 5]);
        $payload['stem'] = 'Teks soal TKP yang diperbaiki pada paket yang sudah dikunci';

        $this->actingAs($this->admin())->put('/admin/soal/'.$question->id, $payload)->assertRedirect();

        $question->refresh();

        $this->assertSame(QuestionCategory::Tkp, $question->category);
        $this->assertSame('Teks soal TKP yang diperbaiki pada paket yang sudah dikunci', $question->stem);
        $this->assertSame([1, 2, 3, 4, 5], $question->options()->pluck('score')->sort()->values()->all());
        $this->assertSame(0, $question->options()->whereNotNull('is_correct')->count());
    }

    public function test_the_list_shows_which_packages_use_an_item(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout Contoh']);
        $package->addQuestion(Question::factory()->twk()->create());

        $response = $this->actingAs($this->admin())->get('/admin/soal');

        $response->assertOk();
        $response->assertSee('1 paket');
        $response->assertSee('Tryout Contoh', escape: false);
    }

    /**
     * A hand-typed admin URL: `?q[]=x` gives the controller an array where it
     * expects a string, and casting that is a 500 for an administrator who mistyped
     * a URL. That is a bad request, not a crash — the same treatment the accounts
     * list already gives it. A normal scalar search must keep filtering.
     */
    public function test_a_non_scalar_search_term_is_treated_as_no_search(): void
    {
        Question::factory()->twk()->create(['stem' => 'Pancasila sebagai dasar negara']);
        Question::factory()->twk()->create(['stem' => 'Pola deret angka berbeda']);

        $response = $this->actingAs($this->admin())->get('/admin/soal?q[]=x&category[]=twk&subcategory[]=pilar_negara');

        $response->assertOk();
        $response->assertSee('Pancasila sebagai dasar negara');
        $response->assertSee('Pola deret angka berbeda');
        $response->assertViewHas('search', '');
        $response->assertViewHas('subcategory', '');
        $response->assertViewHas('category', null);

        // The scalar path is unchanged: a real term still narrows the list.
        $filtered = $this->actingAs($this->admin())->get('/admin/soal?q='.urlencode('Pancasila'));

        $filtered->assertOk();
        $filtered->assertSee('Pancasila sebagai dasar negara');
        $filtered->assertDontSee('Pola deret angka berbeda');
        $filtered->assertViewHas('search', 'Pancasila');
    }

    public function test_an_item_still_used_by_a_package_cannot_be_deleted(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout Contoh']);
        $question = Question::factory()->twk()->create();
        $package->addQuestion($question);

        $this->actingAs($this->admin())
            ->delete('/admin/soal/'.$question->id)
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'masih dipakai di 1 paket'));

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_an_item_answered_by_a_participant_cannot_be_deleted(): void
    {
        $question = Question::factory()->twk()->create();
        $attempt = ExamAttempt::factory()->submitted()->create();
        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);

        $this->actingAs($this->admin())
            ->delete('/admin/soal/'.$question->id)
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'sudah dijawab peserta'));

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_an_unused_item_is_deleted_together_with_its_options(): void
    {
        $question = Question::factory()->twk()->create();
        $optionIds = $question->options()->pluck('id')->all();

        $this->assertCount(5, $optionIds);

        $this->actingAs($this->admin())
            ->delete('/admin/soal/'.$question->id)
            ->assertRedirect()
            ->assertSessionHas('status', 'Soal dihapus dari bank.');

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertSame(0, QuestionOption::query()->whereIn('id', $optionIds)->count());
    }

    public function test_the_create_screen_asks_for_the_subtest_before_it_draws_a_form(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/soal/baru');

        $response->assertOk();
        $response->assertSee('Pilih subtesnya dulu', escape: false);
        $response->assertSee('satu kunci jawaban');
        $response->assertSee('bobot 1-5 per opsi');
        $response->assertDontSee('name="stem"', escape: false);
    }

    public function test_the_keyed_form_offers_a_key_radio_and_no_weights(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/soal/baru?category=twk');

        $response->assertOk();
        $response->assertSee('name="key"', escape: false);
        $response->assertSee('name="options[A][body]"', escape: false);
        $response->assertDontSee('name="options[A][score]"', escape: false);
    }

    public function test_the_tkp_form_offers_weights_and_no_key(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/soal/baru?category=tkp');

        $response->assertOk();
        $response->assertSee('name="options[A][score]"', escape: false);
        $response->assertSee('name="options[E][score]"', escape: false);
        $response->assertSee('masing-masing dipakai sekali');
        $response->assertDontSee('name="key"', escape: false);
    }

    public function test_the_edit_form_opens_with_the_stored_item_and_its_key(): void
    {
        $question = Question::factory()->twk()->create();

        $response = $this->actingAs($this->admin())->get('/admin/soal/'.$question->id.'/ubah');

        $response->assertOk();
        $response->assertSee($question->stem);
        $response->assertSee('Simpan soal');

        // The factory keys each TWK item on a letter of its own (never always A),
        // and the form must open with that key already selected. The assertion
        // reads the radio's own tag rather than a fixed substring, so a no-op
        // reformat of the attribute order in form.blade.php cannot break it — only
        // a genuinely unselected key can.
        $key = $question->correctOption()->label;

        $this->assertTrue(
            $this->keyRadioIsChecked($response->getContent(), $key),
            "The stored key ({$key}) must open prechecked.",
        );
    }

    public function test_the_tkp_weight_ceiling_follows_the_blueprint_and_is_not_a_hard_coded_five(): void
    {
        // 180 / 45 items = a per-item maximum of 4, so the weight ladder must be
        // 1..4 and a weight of 5 must no longer be offered or accepted.
        config(['cpns.subtests.tkp.max' => 180]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/soal/baru?category=tkp')
            ->assertOk()
            ->getContent();

        $pattern = '/<select name="options\[A\]\[score\]"[^>]*>(.*?)<\/select>/s';

        $this->assertMatchesRegularExpression($pattern, $html, 'The TKP form must draw a weight select for option A.');

        preg_match($pattern, $html, $matches);
        $ladder = $matches[1];

        foreach ([1, 2, 3, 4] as $weight) {
            $this->assertStringContainsString('value="'.$weight.'"', $ladder, "The ladder must offer {$weight}.");
        }

        $this->assertStringNotContainsString('value="5"', $ladder, 'A ceiling of 4 must not offer a weight of 5.');

        // The same ceiling governs validation: 5 is out of range, 4 is not.
        $this->actingAs($this->admin())
            ->post('/admin/soal', $this->tkpPayload([5, 4, 3, 2, 1]))
            ->assertSessionHasErrors('options.A.score');

        $this->assertSame(0, Question::query()->count());

        $this->actingAs($this->admin())
            ->post('/admin/soal', $this->tkpPayload([4, 3, 2, 1, 1]))
            ->assertSessionDoesntHaveErrors('options.A.score');
    }

    public function test_the_delete_confirmation_tells_the_truth_about_the_guard(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout Contoh']);
        $package->addQuestion(Question::factory()->twk()->create());

        $response = $this->actingAs($this->admin())->get('/admin/soal');

        $response->assertOk();
        // The old wording promised the package would lose the item a delete no longer
        // performs; the confirmation must describe the refusal instead.
        $response->assertDontSee('kehilangan soal', escape: false);
        $response->assertSee('akan ditolak', escape: false);
        $response->assertSee('belum terpakai akan langsung dihapus', escape: false);
    }

    public function test_the_option_label_set_has_a_single_source_of_truth(): void
    {
        // A..E is declared once, on the model that defines an option, and the
        // controller, the request and the form all read it from there.
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], QuestionOption::LETTERS);

        $response = $this->actingAs($this->admin())->get('/admin/soal/baru?category=tkp');

        $response->assertOk();

        foreach (QuestionOption::LETTERS as $letter) {
            $response->assertSee('name="options['.$letter.'][score]"', escape: false);
        }
    }

    /**
     * True when the key radio carrying $letter is prechecked, whatever order Blade
     * wrote the attributes in.
     */
    private function keyRadioIsChecked(string $html, string $letter): bool
    {
        preg_match_all('/<input\b[^>]*>/i', $html, $matches);

        foreach ($matches[0] as $tag) {
            if (str_contains($tag, 'name="key"') && str_contains($tag, 'value="'.$letter.'"')) {
                return str_contains($tag, 'checked');
            }
        }

        return false;
    }
}
