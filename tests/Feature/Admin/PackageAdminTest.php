<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Support\PackageComposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The package builder.
 *
 * Every refusal in here is checked against the database, not only against the
 * response: a refusal that still wrote is the failure this file exists to catch.
 * Composition (attach, detach, move) is frozen the moment anyone has sat the
 * package, publish is gated on the SKD blueprint, and text edits stay open
 * everywhere — only the list and its order lock.
 */
final class PackageAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * The route key is the slug, so every URL in here is built from the route
     * helper rather than from the primary key.
     */
    private function attachUrl(ExamPackage $package): string
    {
        return route('admin.packages.attach', ['package' => $package]);
    }

    /**
     * The publish button's own attributes, never a substring of the page: the
     * button's class list carries `disabled:opacity-40`, so searching the HTML
     * for the word "disabled" would pass with the attribute gone. The attribute
     * renders as a bare `disabled` followed by whitespace or the tag's end.
     */
    private function publishButtonIsDisabled(string $html): bool
    {
        $pattern = '/<button\b[^>]*>\s*Buka untuk peserta/';

        $this->assertMatchesRegularExpression($pattern, $html, 'The builder must offer the publish button.');

        preg_match($pattern, $html, $button);

        return (bool) preg_match('/\sdisabled(?=[\s>\/])/i', $button[0] ?? '');
    }

    private function detachUrl(ExamPackage $package, Question $question): string
    {
        return route('admin.packages.detach', ['package' => $package, 'question' => $question]);
    }

    private function moveUrl(ExamPackage $package, Question $question): string
    {
        return route('admin.packages.move', ['package' => $package, 'question' => $question]);
    }

    public function test_a_peserta_cannot_reach_the_builder(): void
    {
        $package = ExamPackage::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.packages.builder', $package))
            ->assertForbidden();
    }

    public function test_a_peserta_cannot_publish_a_package(): void
    {
        // The publish gate is the security-relevant boundary of this screen, so the
        // negative control is on the state-changing route, not only on the GET that
        // renders the button.
        $package = ExamPackage::factory()->complete()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.packages.publish', ['package' => $package]))
            ->assertForbidden();

        $this->assertDatabaseHas('exam_packages', [
            'id' => $package->id,
            'status' => ExamPackage::STATUS_DRAFT,
        ]);
    }

    public function test_a_peserta_cannot_change_a_packages_composition(): void
    {
        $package = ExamPackage::factory()->create();
        $member = Question::factory()->twk()->create();
        $spare = Question::factory()->twk()->create();
        $package->addQuestion($member);

        $peserta = User::factory()->create();

        $this->actingAs($peserta)
            ->post($this->attachUrl($package), ['question_id' => $spare->id])
            ->assertForbidden();
        $this->actingAs($peserta)
            ->delete($this->detachUrl($package, $member))
            ->assertForbidden();
        $this->actingAs($peserta)
            ->post($this->moveUrl($package, $member), ['direction' => 'down'])
            ->assertForbidden();

        $this->assertSame(1, $package->questions()->count());
        $this->assertDatabaseHas('exam_package_question', [
            'exam_package_id' => $package->id,
            'question_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('exam_package_question', [
            'exam_package_id' => $package->id,
            'question_id' => $spare->id,
        ]);
    }

    public function test_the_paket_tryout_menu_entry_is_live_in_the_admin_sidebar(): void
    {
        // The sidebar filters its entries through Route::has(), so a route-name
        // mismatch drops the menu item silently rather than failing loudly.
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Paket tryout');
        $response->assertSee('href="'.route('admin.packages.index').'"', escape: false);
    }

    public function test_the_package_form_renders_for_create_and_edit(): void
    {
        $admin = $this->admin();

        $create = $this->actingAs($admin)->get(route('admin.packages.create'));
        $create->assertOk();
        $create->assertSee('name="duration_minutes"', escape: false);
        $create->assertSee('min="'.config('cpns.duration_minutes_min').'"', escape: false);
        $create->assertSee('max="'.config('cpns.duration_minutes_max').'"', escape: false);
        $create->assertSee('value="'.config('cpns.duration_minutes').'"', escape: false);

        $package = ExamPackage::factory()->create(['title' => 'Tryout SKD Perdana']);

        $edit = $this->actingAs($admin)->get(route('admin.packages.edit', $package));
        $edit->assertOk();
        $edit->assertSee('value="Tryout SKD Perdana"', escape: false);
        $edit->assertSee('href="'.route('admin.packages.index').'"', escape: false);
    }

    public function test_the_index_lists_every_package_with_its_shortfall(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout SKD Perdana']);
        $package->addQuestion(Question::factory()->twk()->create());

        $response = $this->actingAs($this->admin())->get(route('admin.packages.index'));

        $response->assertOk();
        $response->assertSee('Tryout SKD Perdana');
        $response->assertSee('TWK kurang 29');
    }

    public function test_creating_a_package_lands_on_the_builder(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.packages.store'), [
                'title' => 'Tryout SKD Perdana',
                'slug' => '',
                'description' => 'Latihan pertama',
                'duration_minutes' => 100,
            ])
            ->assertRedirect(route('admin.packages.builder', ExamPackage::query()->firstOrFail()));

        $package = ExamPackage::query()->firstOrFail();

        $this->assertSame('tryout-skd-perdana', $package->slug);
        $this->assertSame(ExamPackage::STATUS_DRAFT, $package->status);
    }

    public function test_a_duplicate_slug_is_refused(): void
    {
        ExamPackage::factory()->create(['title' => 'Tryout SKD Perdana', 'slug' => 'tryout-skd-perdana']);

        $this->actingAs($this->admin())
            ->post(route('admin.packages.store'), [
                'title' => 'Tryout SKD Perdana',
                'slug' => '',
                'duration_minutes' => 100,
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, ExamPackage::query()->count());
    }

    public function test_editing_a_package_keeps_its_own_slug(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout SKD Perdana', 'slug' => 'tryout-skd-perdana']);

        $this->actingAs($this->admin())
            ->put(route('admin.packages.update', ['package' => $package]), [
                'title' => 'Tryout SKD Perdana',
                'slug' => 'tryout-skd-perdana',
                'duration_minutes' => 130,
            ])
            ->assertRedirect(route('admin.packages.builder', $package));

        $this->assertSame(130, $package->refresh()->duration_minutes);
    }

    public function test_removing_an_item_keeps_the_rest_in_order_and_the_next_move_renumbers(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(3)->create();

        foreach ($questions as $question) {
            $this->actingAs($this->admin())
                ->post($this->attachUrl($package), ['question_id' => $question->id]);
        }

        $this->assertSame([1, 2, 3], $package->questions()->get()->pluck('pivot.position')->all());

        $this->actingAs($this->admin())->delete($this->detachUrl($package, $questions[1]));

        $this->assertSame(2, $package->questions()->count());
        $this->assertSame(
            [$questions[0]->id, $questions[2]->id],
            $package->questions()->get()->pluck('id')->all(),
            'The remaining items keep their relative order.',
        );

        // Detach does not renumber, so the stored positions become [1, 3]. That gap
        // is the intended contract, not a bug: the builder numbers rows by
        // collection index, attach appends at max + 1, and the first move renumbers
        // the whole list to 1..N — which the tail of this test pins.
        $this->assertSame([1, 3], $package->questions()->get()->pluck('pivot.position')->all());

        $this->actingAs($this->admin())
            ->post($this->moveUrl($package, $questions[2]), ['direction' => 'up'])
            ->assertRedirect();

        $ordered = $package->questions()->get();

        $this->assertSame([$questions[2]->id, $questions[0]->id], $ordered->pluck('id')->all());
        $this->assertSame([1, 2], $ordered->pluck('pivot.position')->all());
    }

    public function test_adding_the_same_item_twice_changes_nothing(): void
    {
        $package = ExamPackage::factory()->create();
        $question = Question::factory()->twk()->create();

        $this->actingAs($this->admin())->post($this->attachUrl($package), ['question_id' => $question->id]);
        $this->actingAs($this->admin())->post($this->attachUrl($package), ['question_id' => $question->id]);

        $this->assertSame(1, $package->questions()->count());
    }

    public function test_an_unknown_item_cannot_be_attached(): void
    {
        $package = ExamPackage::factory()->create();

        $this->actingAs($this->admin())
            ->post($this->attachUrl($package), ['question_id' => 999_999])
            ->assertSessionHasErrors('question_id');

        $this->assertSame(0, $package->questions()->count());
    }

    public function test_a_retired_item_cannot_be_attached(): void
    {
        // The builder's candidate list is filtered through Question::active(), so a
        // retired item is invisible in the UI — but a hand-crafted POST is not, and
        // the participant flow reads the pivot with no is_active filter at all. The
        // refusal has to happen in the controller, on the way in.
        $package = ExamPackage::factory()->create();
        $retired = Question::factory()->twk()->create(['is_active' => false]);

        $this->actingAs($this->admin())
            ->post($this->attachUrl($package), ['question_id' => $retired->id])
            ->assertSessionHasErrors('question_id');

        $this->assertSame(0, $package->questions()->count());
        $this->assertDatabaseMissing('exam_package_question', [
            'exam_package_id' => $package->id,
            'question_id' => $retired->id,
        ]);
    }

    public function test_reordering_swaps_two_items_without_tripping_the_unique_position(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(3)->create();

        foreach ($questions as $question) {
            $package->addQuestion($question);
        }

        $this->actingAs($this->admin())
            ->post($this->moveUrl($package, $questions[2]), ['direction' => 'up'])
            ->assertRedirect();

        $ordered = $package->questions()->get();

        $this->assertSame([$questions[0]->id, $questions[2]->id, $questions[1]->id], $ordered->pluck('id')->all());
        $this->assertSame([1, 2, 3], $ordered->pluck('pivot.position')->all());
    }

    public function test_moving_the_first_item_up_is_a_no_op(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(2)->create();

        foreach ($questions as $question) {
            $package->addQuestion($question);
        }

        $this->actingAs($this->admin())
            ->post($this->moveUrl($package, $questions[0]), ['direction' => 'up']);

        $this->assertSame($questions->pluck('id')->all(), $package->questions()->get()->pluck('id')->all());
    }

    public function test_publishing_is_refused_until_the_composition_is_exact(): void
    {
        $package = ExamPackage::factory()->create();
        $package->addQuestion(Question::factory()->twk()->create());

        $this->actingAs($this->admin())
            ->post(route('admin.packages.publish', ['package' => $package]))
            ->assertSessionHasErrors('package');

        $this->assertDatabaseHas('exam_packages', [
            'id' => $package->id,
            'status' => ExamPackage::STATUS_DRAFT,
        ]);
    }

    public function test_publishing_is_refused_at_a_wrong_category_split(): void
    {
        $package = ExamPackage::factory()->create();

        foreach (['twk' => 31, 'tiu' => 34, 'tkp' => 45] as $category => $count) {
            $enum = QuestionCategory::from($category);
            for ($i = 0; $i < $count; $i++) {
                $package->addQuestion(Question::factory()->category($enum)->create());
            }
        }

        $this->assertSame((int) config('cpns.total_items'), $package->questions()->count());
        $this->assertFalse(PackageComposition::isComplete($package));

        $this->actingAs($this->admin())
            ->post(route('admin.packages.publish', ['package' => $package]))
            ->assertSessionHasErrors('package');

        $this->assertDatabaseHas('exam_packages', [
            'id' => $package->id,
            'status' => ExamPackage::STATUS_DRAFT,
        ]);
    }

    public function test_a_complete_package_publishes_and_can_be_closed_again(): void
    {
        $package = ExamPackage::factory()->complete()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.packages.publish', ['package' => $package]))
            ->assertRedirect();

        $this->assertTrue($package->refresh()->isPublished());

        $this->actingAs($this->admin())
            ->post(route('admin.packages.unpublish', ['package' => $package]))
            ->assertRedirect();

        $this->assertFalse($package->refresh()->isPublished());
    }

    public function test_the_builder_shows_the_shortfall_bar_and_the_bank_picks(): void
    {
        $package = ExamPackage::factory()->create();
        $package->addQuestion(Question::factory()->twk()->create());
        $candidate = Question::factory()->tiu()->create(['stem' => 'Soal TIU yang belum masuk paket']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.packages.builder', ['package' => $package]));

        $response->assertOk();
        $response->assertSee('1 / 30');          // the TWK cell of the shortfall bar
        $response->assertSee('0 / 35');          // TIU, still empty
        $response->assertSee('0 / 45');          // TKP, still empty
        $response->assertSee('kurang 29 soal lagi');
        $response->assertSee('Soal TIU yang belum masuk paket');
        $response->assertSee('skor maks '.config('cpns.total_max'));
        $response->assertDontSee('menit · maks');
        $response->assertSee('Buka untuk peserta');
        // The button is disabled while the composition is short, but the refusal
        // itself is enforced in the controller (see the publish tests above).
        $this->assertTrue(
            $this->publishButtonIsDisabled($response->getContent()),
            'A short composition must render the publish button disabled.',
        );

        // The other half of that control, which is what makes it non-vacuous: the
        // same button on an even package must carry no disabled attribute at all.
        $complete = ExamPackage::factory()->complete()->create();

        $this->assertFalse(
            $this->publishButtonIsDisabled(
                $this->actingAs($this->admin())
                    ->get(route('admin.packages.builder', ['package' => $complete]))
                    ->getContent(),
            ),
            'An even composition must render the publish button enabled.',
        );
    }

    public function test_the_builder_hides_the_composition_controls_on_a_locked_package(): void
    {
        $package = ExamPackage::factory()->complete()->published()->create();
        ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->submitted()
            ->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.packages.builder', ['package' => $package]));

        $response->assertOk();
        $response->assertSee('Komposisi paket ini dikunci', escape: false);
        $response->assertDontSee('Tambah dari bank soal', escape: false);
    }

    public function test_the_dashboard_shortfall_button_points_at_the_builder(): void
    {
        $package = ExamPackage::factory()->create();
        $package->addQuestion(Question::factory()->twk()->create());

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Susun paket');
        $response->assertSee('href="'.route('admin.packages.builder', $package).'"', escape: false);
    }

    public function test_the_composition_locks_once_someone_has_sat_the_package(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $question = $package->questions()->first();
        $spare = Question::factory()->twk()->create();

        ExamAttempt::factory()->forUserAndPackage($user, $package)->submitted()->create();

        $this->assertTrue($package->refresh()->isLocked());

        $this->actingAs($this->admin())
            ->post($this->attachUrl($package), ['question_id' => $spare->id])
            ->assertSessionHasErrors('package');

        $this->assertSame(
            (int) config('cpns.total_items'),
            $package->questions()->count(),
            'A refused attach must not have written.',
        );
        $this->assertDatabaseMissing('exam_package_question', [
            'exam_package_id' => $package->id,
            'question_id' => $spare->id,
        ]);

        $this->actingAs($this->admin())
            ->delete($this->detachUrl($package, $question))
            ->assertSessionHasErrors('package');

        $this->assertSame(
            (int) config('cpns.total_items'),
            $package->questions()->count(),
            'A refused detach must not have written.',
        );
        $this->assertDatabaseHas('exam_package_question', [
            'exam_package_id' => $package->id,
            'question_id' => $question->id,
        ]);

        // Editing the item itself still works: only the composition is frozen.
        $this->actingAs($this->admin())->put(route('admin.questions.update', ['question' => $question]), [
            'category' => $question->category->value,
            'subcategory' => $question->subcategory,
            'stem' => 'Teks soal yang diperbaiki pada paket yang sudah dikunci',
            'explanation' => null,
            'is_active' => '1',
            'key' => $question->correctOption()->label,
            'options' => collect(['A', 'B', 'C', 'D', 'E'])
                ->mapWithKeys(fn (string $letter): array => [$letter => ['body' => 'Opsi '.$letter]])
                ->all(),
        ])->assertRedirect();

        $this->assertSame('Teks soal yang diperbaiki pada paket yang sudah dikunci', $question->refresh()->stem);
    }

    public function test_a_locked_package_refuses_a_move_without_moving_anything(): void
    {
        $package = ExamPackage::factory()->complete()->published()->create();
        ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->submitted()
            ->create();

        $ordered = $package->questions()->get();
        $before = $ordered->map(fn (Question $item): array => [$item->id, (int) $item->pivot->position])->all();

        $this->actingAs($this->admin())
            ->post($this->moveUrl($package, $ordered[2]), ['direction' => 'up'])
            ->assertSessionHasErrors('package');

        $after = $package->questions()->get()
            ->map(fn (Question $item): array => [$item->id, (int) $item->pivot->position])
            ->all();

        $this->assertSame($before, $after, 'A refused move must leave the order untouched.');
    }

    public function test_an_unlocked_draft_package_still_allows_detach(): void
    {
        // Task 13's delete refusal tells the admin to take the item out of its
        // package first; on a draft nobody has sat, that instruction must work.
        $package = ExamPackage::factory()->create();
        $question = Question::factory()->twk()->create();
        $package->addQuestion($question);
        $package->addQuestion(Question::factory()->twk()->create());

        $this->actingAs($this->admin())
            ->delete($this->detachUrl($package, $question))
            ->assertRedirect()
            ->assertSessionHas('status', 'Soal dikeluarkan dari paket.');

        $this->assertSame(1, $package->questions()->count());
        $this->assertDatabaseMissing('exam_package_question', [
            'exam_package_id' => $package->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_an_item_outside_the_package_cannot_be_detached(): void
    {
        // A crafted URL can name a question that was never in this package. Nothing
        // would be written either way, but the admin would be told "Soal dikeluarkan
        // dari paket." over a list that still holds it — a success message they
        // would have to disprove by reading the list again.
        $package = ExamPackage::factory()->create();
        $member = Question::factory()->twk()->create();
        $stranger = Question::factory()->twk()->create();
        $package->addQuestion($member);

        $this->actingAs($this->admin())
            ->delete($this->detachUrl($package, $stranger))
            ->assertSessionHasErrors('package')
            ->assertSessionMissing('status');

        $this->assertSame(1, $package->questions()->count());
        $this->assertDatabaseHas('exam_package_question', [
            'exam_package_id' => $package->id,
            'question_id' => $member->id,
        ]);
    }

    public function test_an_item_outside_the_package_cannot_be_moved(): void
    {
        $package = ExamPackage::factory()->create();
        $package->addQuestion(Question::factory()->twk()->create());
        $package->addQuestion(Question::factory()->twk()->create());
        $stranger = Question::factory()->twk()->create();

        $before = $package->questions()->get()
            ->map(fn (Question $item): array => [$item->id, (int) $item->pivot->position])
            ->all();

        $this->actingAs($this->admin())
            ->post($this->moveUrl($package, $stranger), ['direction' => 'up'])
            ->assertSessionHasErrors('package');

        $after = $package->questions()->get()
            ->map(fn (Question $item): array => [$item->id, (int) $item->pivot->position])
            ->all();

        $this->assertSame($before, $after, 'A refusal must leave the order untouched.');
    }

    /**
     * The same bad-URL guard as the bank list: `?q[]=x` gives the builder an array
     * where it expects a string, and casting that is a 500 for an administrator who
     * mistyped a URL. A real search term must still narrow the candidate list.
     */
    public function test_a_non_scalar_search_term_is_treated_as_no_search(): void
    {
        $package = ExamPackage::factory()->create();
        Question::factory()->twk()->create(['stem' => 'Pancasila di daftar calon']);
        Question::factory()->twk()->create(['stem' => 'Deret angka di daftar calon']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.packages.builder', ['package' => $package, 'q' => ['x']]));

        $response->assertOk();
        $response->assertSee('Pancasila di daftar calon');
        $response->assertSee('Deret angka di daftar calon');
        $response->assertViewHas('search', '');

        // The scalar path is unchanged: a real term still narrows the list.
        $filtered = $this->actingAs($this->admin())
            ->get(route('admin.packages.builder', ['package' => $package, 'q' => 'Pancasila']));

        $filtered->assertOk();
        $filtered->assertSee('Pancasila di daftar calon');
        $filtered->assertDontSee('Deret angka di daftar calon');
        $filtered->assertViewHas('search', 'Pancasila');
    }
}
