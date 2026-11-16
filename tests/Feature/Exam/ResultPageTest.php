<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use App\Services\Exam\ExamGrader;
use App\Services\Exam\ScoreReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class ResultPageTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    /**
     * @return array{0: User, 1: ExamAttempt}
     */
    private function gradedPaper(string $strategy = 'best'): array
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, $strategy);

        return [$user, app(ExamGrader::class)->grade($attempt)];
    }

    public function test_an_unsubmitted_sitting_shows_no_numbers(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertRedirect(route('tryout.room', $attempt));
    }

    public function test_another_participants_result_is_forbidden(): void
    {
        [, $attempt] = $this->gradedPaper();

        $this->actingAs(User::factory()->create())
            ->get('/hasil/'.$attempt->id)
            ->assertForbidden();
    }

    public function test_the_result_page_shows_the_total_the_thresholds_and_the_explanations(): void
    {
        [$user, $attempt] = $this->gradedPaper();

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();

        // The earned total, read out of the element that prints it. A bare
        // `assertSee('550')` was satisfied by the "dari 550" ceiling line beside it,
        // so it would have stayed green with the score element rendering 0.
        $this->assertMatchesRegularExpression(
            '/Skor total<\/p>\s*<p[^>]*>\s*550\s*<\/p>/',
            (string) $response->getContent(),
            'The score element must print the earned total.',
        );

        $response->assertSee('Lolos ambang batas');
        $response->assertSee('TWK');
        $response->assertSee('TIU');
        $response->assertSee('TKP');
        $response->assertSee('Pembahasan');
        // The tick is a positioned element carrying its own label. "Ambang batas"
        // on its own is ordinary prose on this page and proves nothing.
        $twkThreshold = QuestionCategory::Twk->threshold();
        $response->assertSee('title="Ambang batas '.$twkThreshold.'"', escape: false);
        $response->assertSee(
            'left: '.round($twkThreshold / QuestionCategory::Twk->maxScore() * 100, 1).'%',
            escape: false,
        );
    }

    /**
     * A non-perfect paper, so the earned total cannot be confused with the ceiling
     * printed beside it. `worst` answers every keyed item wrong and every TKP item
     * at weight 1: 0 + 0 + 45.
     */
    public function test_the_result_page_prints_the_earned_total_of_an_imperfect_paper(): void
    {
        [$user, $attempt] = $this->gradedPaper('worst');

        $this->assertSame(45, $attempt->total_score, 'The fixture is not a perfect paper.');

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();

        $this->assertMatchesRegularExpression(
            '/Skor total<\/p>\s*<p[^>]*>\s*45\s*<\/p>/',
            (string) $response->getContent(),
            'The score element must print the earned total it stored, not the ceiling.',
        );

        // The consequence that follows from 45 of 550, stated on the page.
        $response->assertSee('Belum lolos ambang batas');
        $this->assertFalse($attempt->hasPassed());
    }

    /**
     * The failing verdict and the per-subtest shortfall had no coverage at all, so
     * inverting the pass/fail ternary kept the whole suite green. For a CPNS tryout,
     * telling a failing candidate they passed is the worst bug this product can
     * have: the failing branch is asserted here, and the passing branch is asserted
     * NOT to show it in the mirror test below.
     */
    public function test_a_failing_paper_is_told_it_failed_and_which_subtests_fell_short(): void
    {
        [$user, $attempt] = $this->gradedPaper('blank');
        $report = new ScoreReport($attempt);

        $this->assertFalse($report->passed(), 'A blank paper cannot pass.');
        $this->assertSame(0, $report->total());

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();
        $response->assertSee('Belum lolos ambang batas');

        // Each subtest names how far short it fell, computed from the blueprint
        // rather than retyped here.
        foreach (QuestionCategory::cases() as $category) {
            $shortfall = (int) $report->subtests()[$category->value]['shortfall'];

            $this->assertGreaterThan(0, $shortfall, "{$category->value} must have fallen short.");
            $response->assertSee('kurang '.$shortfall.' poin');
        }
    }

    /**
     * The mirror: a paper that passed may not carry the failing verdict or a
     * shortfall sentence anywhere.
     */
    public function test_a_passing_paper_shows_neither_the_failing_verdict_nor_a_shortfall(): void
    {
        [$user, $attempt] = $this->gradedPaper('best');

        $report = new ScoreReport($attempt);

        $this->assertTrue($report->passed());
        $this->assertSame([0, 0, 0], array_values(array_map(
            fn (array $subtest): int => (int) $subtest['shortfall'],
            $report->subtests(),
        )));

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();
        $response->assertSee('Lolos ambang batas');
        $response->assertDontSee('Belum lolos ambang batas');

        // 'kurang' is printed by exactly one branch of the per-subtest line, so its
        // absence is the half that a passing paper has to prove.
        $response->assertDontSee('kurang');
    }

    public function test_the_report_reads_the_frozen_scores(): void
    {
        [, $attempt] = $this->gradedPaper();
        $report = new ScoreReport($attempt);

        $this->assertSame(550, $report->total());
        $this->assertSame(550, $report->maximum());
        $this->assertTrue($report->passed());

        $subtests = $report->subtests();

        $this->assertSame(150, $subtests['twk']['score']);
        $this->assertSame(65, $subtests['twk']['threshold']);
        $this->assertSame(0, $subtests['twk']['blank']);
        $this->assertFalse($subtests['twk']['has_key'] === false);
        $this->assertSame(5.0, $subtests['tkp']['average_weight']);
        $this->assertNull($subtests['twk']['average_weight']);
    }

    public function test_editing_a_question_afterwards_does_not_move_a_finished_score(): void
    {
        [, $attempt] = $this->gradedPaper();
        $question = $attempt->package->questions()->first();

        $question->options()->update(['is_correct' => false]);
        $question->forceFill(['stem' => 'Teks soal yang diperbaiki setelah ujian'])->save();

        $report = new ScoreReport($attempt->refresh());

        $this->assertSame(550, $report->total());
        // The text does reach past participants, which is the point of reading it live.
        $this->assertStringContainsString('diperbaiki setelah ujian', $report->review()->first()['question']->stem);
    }

    public function test_the_review_filter_narrows_to_one_status(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        // Exactly one TWK item is pointed at a wrong option before the paper is
        // graded, so "wrong" has a known size instead of whatever the fixture did.
        $twk = $package->questions()->category(QuestionCategory::Twk)->first();
        $wrongOption = $twk->options->firstWhere('is_correct', false);

        $attempt->answers()
            ->where('question_id', $twk->id)
            ->update(['selected_option_id' => $wrongOption->id]);

        $graded = app(ExamGrader::class)->grade($attempt->refresh());
        $report = new ScoreReport($graded);

        $this->assertSame(1, $report->review('wrong')->count());
        // The keyed items that were answered right (TWK 30 + TIU 35, less the one
        // pointed at a wrong option). The 45 TKP items are answered, not "benar".
        $keyedItems = QuestionCategory::Twk->itemCount() + QuestionCategory::Tiu->itemCount();
        $this->assertSame($keyedItems - 1, $report->review('correct')->count());
        $this->assertSame(QuestionCategory::Tkp->itemCount(), $report->review('answered')->count());

        $this->actingAs($user)
            ->get('/hasil/'.$graded->id.'?filter=wrong')
            ->assertOk()
            ->assertSee('salah');

        $this->actingAs($user)
            ->get('/hasil/'.$graded->id.'?filter=answered')
            ->assertOk()
            ->assertSee('terjawab');
    }

    public function test_the_subtopic_breakdown_is_ordered_weakest_first(): void
    {
        [, $attempt] = $this->gradedPaper();
        $report = new ScoreReport($attempt);

        $ratios = $report->subtopics()->pluck('ratio')->all();
        $sorted = $ratios;
        sort($sorted);

        $this->assertSame($sorted, $ratios);
        $this->assertNotEmpty($ratios);
    }

    public function test_a_tkp_subtopic_ranks_by_points_captured_not_by_answered_count(): void
    {
        // Weight 1 on every TKP item. Every item is answered, so the old
        // correct/answered ratio read 100% and hid four fifths of the loss.
        [, $attempt] = $this->gradedPaper('worst');
        $rows = (new ScoreReport($attempt))->subtopics();

        $perItem = intdiv(QuestionCategory::Tkp->maxScore(), QuestionCategory::Tkp->itemCount());

        $tkp = $rows->where('category', 'TKP');
        $this->assertNotEmpty($tkp);

        foreach ($tkp as $row) {
            $this->assertSame($row['total'] * $perItem, $row['available']);
            $this->assertSame(20.0, $row['ratio']);
            $this->assertLessThan(100.0, $row['ratio']);
        }

        // On the same metric a fully-missed keyed subtopic reads 0, so the numbers
        // are comparable across all three categories instead of each on its own scale.
        foreach ($rows->whereIn('category', ['TWK', 'TIU']) as $row) {
            $this->assertSame(0, $row['score']);
            $this->assertSame(0.0, $row['ratio']);
        }
    }

    public function test_the_timing_block_is_labelled_as_an_estimate(): void
    {
        [$user, $attempt] = $this->gradedPaper();
        $timing = (new ScoreReport($attempt))->timing();

        $this->assertSame(6000, $timing['budget_seconds']);
        $this->assertCount(5, $timing['slowest']);

        // The estimate is a sentence the participant reads, not a flag on the read
        // model, so deleting it from the partial has to fail this test.
        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('Angka waktu adalah perkiraan');
    }

    public function test_a_tkp_answer_is_presented_as_answered_not_as_right_or_wrong(): void
    {
        // Weight 1 throughout, so nothing here is a "miss" either: TKP simply has
        // no key, and the page must not pretend otherwise.
        [$user, $attempt] = $this->gradedPaper('worst');

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id.'?filter=answered');

        $response->assertOk();
        $response->assertSee('terjawab');
        $response->assertSee('jawabanmu');
        // A TKP-only page carries no right/wrong marking anywhere. The marker is
        // the row's own verdict word rather than a colour utility, so a restyle
        // cannot turn this check into one that cannot fail.
        $response->assertDontSee('data-option-tone="danger"', escape: false);
        $this->assertSame(0, substr_count((string) $response->getContent(), 'data-option-tone="success"'));
    }

    public function test_a_re_keyed_question_still_shows_the_frozen_verdict(): void
    {
        [$user, $attempt] = $this->gradedPaper('best');
        $question = $attempt->package->questions()->first();

        // An admin moves the key after the sitting, so no option is correct any
        // more — the page's own frozen-score test builds this exact state.
        $question->options()->update(['is_correct' => false]);

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();

        // Every keyed item was answered right at grading time, so every one of
        // those picks is still drawn as the key, and nothing on the page is marked
        // wrong. Counted from the rows' own verdict words, not from colours.
        $keyed = QuestionCategory::Twk->itemCount() + QuestionCategory::Tiu->itemCount();
        $html = (string) $response->getContent();

        $this->assertSame($keyed, substr_count($html, 'data-option-tone="success"'));
        $this->assertSame(0, substr_count($html, 'data-option-tone="danger"'));
    }

    public function test_a_second_sitting_adds_a_history_row_and_a_delta(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();

        $first = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($first, 'best');
        app(ExamGrader::class)->grade($first);

        $second = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($second, 'blank');
        $graded = app(ExamGrader::class)->grade($second);

        $history = (new ScoreReport($graded))->history();

        $this->assertCount(2, $history);
        $this->assertNull($history[0]['delta']);
        $this->assertSame(-550, $history[1]['delta']);
        $this->assertTrue($history[1]['is_current']);
        $this->assertSame(2, (new ScoreReport($graded))->chart()['count']);
    }

    public function test_an_expired_sitting_is_graded_and_readable(): void
    {
        [$user, $attempt] = $this->gradedPaper();

        $attempt->forceFill([
            'status' => AttemptStatus::Expired,
            'total_score' => 300,
            'twk_score' => 100,
            'tiu_score' => 100,
            'tkp_score' => 100,
        ])->save();

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('waktu habis');
    }

    /**
     * The review is a list of collapsed rows, not 110 expanded articles.
     *
     * Rendered open, the page was 55,000px tall and there was no way back to a
     * particular item. The check is on the elements themselves: a bare
     * `assertSee('</details>')` would pass on a page where the attribute that
     * actually collapses them had been dropped.
     */
    public function test_every_review_item_is_a_collapsed_disclosure(): void
    {
        [$user, $attempt] = $this->gradedPaper();

        $html = (string) $this->actingAs($user)->get('/hasil/'.$attempt->id)->getContent();

        $this->assertStringContainsString('<details', $html, 'The review must be disclosure rows.');

        // None of the rows may ship `open`, or the page is the wall of text again.
        $this->assertDoesNotMatchRegularExpression(
            '/<details[^>]*\sopen(\s|>)/',
            $html,
            'A review row rendered open defeats the collapsed list.',
        );

        // A closed row still has to say what the item is about, otherwise the
        // list cannot be scanned and collapsing it only hid the review.
        $this->assertMatchesRegularExpression('/<summary[\s\S]*Soal 1\b/', $html);

        // The control that opens every row is for the browser, so the markup must
        // ship it hidden: a visible button that does nothing is worse than none.
        $this->assertMatchesRegularExpression('/<button[^>]*data-review-toggle-all[^>]*\shidden/', $html);
    }

    /**
     * The filter tabs stop reloading the page by fetching the review card's body
     * alone and swapping it in. That only holds if the body really is a fragment:
     * if it arrived wrapped in a second card, the module would still be listening
     * to the outer one and would swap into the wrong element — and the page would
     * grow a nested copy of the list on every tab press.
     */
    public function test_the_review_card_can_be_fetched_as_a_body_without_its_own_wrapper(): void
    {
        [$user, $attempt] = $this->gradedPaper('best');

        $page = (string) $this->actingAs($user)->get('/hasil/'.$attempt->id)->getContent();

        $this->assertSame(
            1,
            substr_count($page, 'data-review>'),
            'The full page must declare the review card exactly once.',
        );

        $body = (string) $this->actingAs($user)
            ->withHeader('X-Review-Partial', '1')
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->getContent();

        // Not a document: no other card of the page came with it.
        $this->assertStringNotContainsString('<html', $body);
        $this->assertStringNotContainsString('Skor total', $body);
        $this->assertStringNotContainsString('Kembali ke daftar tryout', $body);

        // A body: the list, the rows, and the controls that have to be replaced.
        $this->assertStringContainsString('data-review-list', $body);
        $this->assertStringContainsString('data-review-tab', $body);
        $this->assertStringContainsString('data-review-toggle-all', $body);
        $this->assertStringContainsString('<details', $body);

        $this->assertSame(
            0,
            substr_count($body, 'data-review>'),
            'The fragment must not bring its own card: the page already has one.',
        );
    }

    /**
     * A fragment is the same read as the page it comes from, so it cannot be a
     * way around the gates. Fetched with a header it must refuse exactly what the
     * page refuses, or the header becomes a way to read an unfinished sitting.
     */
    public function test_the_review_fragment_is_subject_to_the_same_gates_as_the_page(): void
    {
        [, $attempt] = $this->gradedPaper();

        $this->actingAs(User::factory()->create())
            ->withHeader('X-Review-Partial', '1')
            ->get('/hasil/'.$attempt->id)
            ->assertForbidden();

        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $open = app(AttemptManager::class)->startOrResume($user, $package);

        $this->actingAs($user)
            ->withHeader('X-Review-Partial', '1')
            ->get('/hasil/'.$open->id)
            ->assertRedirect(route('tryout.room', $open));
    }

    /**
     * The fragment reads the query string like the page does, and marks the tab it
     * arrived for — that marking is where the browser puts focus back after a swap,
     * so a fragment that lost it would drop a keyboard user at the top of the
     * document, which is the whole thing this replaces.
     */
    public function test_the_fragment_narrows_the_list_and_marks_the_tab_it_arrived_for(): void
    {
        [$user, $attempt] = $this->gradedPaper('best');

        $body = (string) $this->actingAs($user)
            ->withHeader('X-Review-Partial', '1')
            ->get('/hasil/'.$attempt->id.'?filter=wrong')
            ->assertOk()
            ->getContent();

        // A perfect paper has nothing wrong, so the list has to come back empty.
        // Rendering every row regardless would still look correct in a browser.
        // The fragment check keeps this from passing on a full page, which has the
        // same list and the same marking.
        $this->assertStringNotContainsString('<html', $body);
        $this->assertStringContainsString('Tidak ada soal yang cocok dengan filter ini.', $body);
        $this->assertStringNotContainsString('<details', $body);

        $this->assertSame(1, substr_count($body, 'aria-current="page"'));

        preg_match('/<a\b[^>]*aria-current="page"[^>]*>/', $body, $current);

        $this->assertNotEmpty($current, 'The fragment must mark the tab it arrived for.');
        $this->assertStringContainsString('filter=wrong', $current[0]);
    }

    /**
     * The fragment answers only when it was asked for. A tab is an ordinary link,
     * so without scripting the very same request has to render the whole page.
     */
    public function test_the_fragment_header_is_required_and_anything_else_gets_the_page(): void
    {
        [$user, $attempt] = $this->gradedPaper('best');

        $response = $this->actingAs($user)
            ->withHeader('X-Review-Partial', 'no')
            ->get('/hasil/'.$attempt->id)
            ->assertOk();

        $this->assertStringContainsString('<html', (string) $response->getContent());
        $this->assertStringContainsString('Skor total', (string) $response->getContent());
    }
}
