<?php

/*
 * A throwaway participant with a finished sitting, for the browser smokes.
 *
 * Kept next to them rather than in /tmp because /tmp does not survive a reboot and
 * a smoke test that cannot be re-run is a smoke test nobody runs. It is the same
 * shape as the seeder the PHP suite uses (tests/Concerns/SitsExam.php): answer the
 * whole paper, then grade it, so the sitting is finished and its numbers exist.
 *
 * The AI state of that sitting is deliberately wiped on every run — the analysis,
 * the conversation around it, and the item threads — so the smokes always start
 * from the state a first-time participant sees: a finished sitting with nothing
 * generated, and the button that generates it. Re-running is therefore safe, and
 * the second run costs the same as the first.
 *
 * It does NOT write an analysis. The analysis text on the page has to be the
 * model's, or the smoke is verifying a fixture; tools/analysis-chat-smoke.mjs
 * presses the button and waits for the real thing.
 *
 * Usage, from the repository root, on the host that serves the site:
 *
 *   php artisan tinker --execute="require 'tools/smoke-paper.php';" | tail -1
 *
 * SMOKE_EMAIL picks the account (default chat-smoke@example.test). It must be a
 * throwaway: the sitting it creates is real, and the smokes spend real tokens
 * against it. Delete the account when you are done.
 */

use App\Models\AttemptAnalysis;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\QuestionChat;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use App\Services\Exam\ExamGrader;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$email = (string) (getenv('SMOKE_EMAIL') ?: 'chat-smoke@example.test');

$user = User::query()->firstOrCreate(
    ['email' => $email],
    ['name' => 'Peserta Uji', 'password' => Hash::make(Str::random(40))],
);

// The real paper, not a factory: the smoke should drive the package participants
// actually sit, with its 110 items and its own subtest weights.
//
// Both column names below are spelled out because getting them wrong here does
// not fail — on SQLite an unknown identifier in a where clause is read as a
// string literal, so `whereNotNull('published_at')` matched every row and
// `where('package_id', ...)` matched none, and a seeder that silently does the
// wrong thing is worse than one that crashes.
$package = ExamPackage::query()->where('status', ExamPackage::STATUS_PUBLISHED)->orderBy('id')->first();

if ($package === null) {
    fwrite(STDERR, "No published package to sit.\n");

    return;
}

// A fresh sitting every run, because a submitted attempt is frozen: its answers
// are what they were when it was graded, so re-answering one is writing rows
// nothing reads and grading it changes nothing. (Found the hard way — the seeder
// looked like it worked and the analysis kept describing a perfect paper.) The
// previous fixtures are removed first, so the account holds exactly one paper and
// the live database does not fill up with them.
foreach (ExamAttempt::query()->where('user_id', $user->id)->get() as $stale) {
    $stale->delete();
}

$attempt = app(AttemptManager::class)->startOrResume($user, $package);

// Answer every item and grade it, so the sitting is finished and scored. The
// first sixty percent of the paper is answered well and the rest poorly, and since
// the paper runs TWK, then TIU, then TKP, that lands as a participant who is fine
// on the first two subtests and weak on the third — the common real shape, and the
// one worth analysing. A perfect paper has no gaps for the model to find, and a
// blank one has nothing else.
//
// The timestamps walk forward at a plausible pace rather than all landing on the
// same second. The analysis is told how long the sitting took and where the quick
// guesses were, and a paper answered in zero seconds makes that section
// meaningless.
$questions = $package->questions()->with('options')->get();
$cut = (int) floor($questions->count() * 0.6);

$answeredAt = now()->subSeconds(55 * max(1, $questions->count()));

foreach ($questions as $index => $question) {
    $correct = $index < $cut;

    if ($question->category->requiresKey()) {
        $option = $question->options->firstWhere('is_correct', $correct);
    } else {
        // TKP has no key: what separates a good answer from a careless one is the
        // option's weight.
        $option = $correct
            ? $question->options->sortByDesc('score')->first()
            : $question->options->sortBy('score')->first();
    }

    $answeredAt = $answeredAt->copy()->addSeconds($index < $cut ? 35 : 75);

    // `seconds_spent` is accumulated by the answer recorder as the participant
    // navigates, which this seeder bypasses: without it every answer reads as
    // taking no time and the analysis says so. A handful of the misses are very
    // fast, which is what the "guessed" line in the analysis is counting.
    $guess = ! $correct && $question->category->requiresKey() && $index % 7 === 0;

    $attempt->answers()->updateOrCreate(
        ['question_id' => $question->id],
        [
            'selected_option_id' => $option?->id,
            'answered_at' => $answeredAt,
            'seconds_spent' => $guess ? 6 : ($correct ? 40 : 70),
        ],
    );
}

// The sitting also has to have taken a plausible amount of time: the analysis is
// told how much of the 100 minutes was used, and a paper started and handed in in
// the same second makes that line meaningless.
$attempt->forceFill(['started_at' => now()->subMinutes(95)])->save();

$attempt = app(ExamGrader::class)->grade($attempt);

// Back to the state a first-time participant sees.
$attempt->analysis()->get()->each(function (AttemptAnalysis $analysis): void {
    $analysis->messages()->delete();
    $analysis->delete();
});

QuestionChat::query()->where('exam_attempt_id', $attempt->id)->each(function (QuestionChat $chat): void {
    $chat->messages()->delete();
    $chat->delete();
});

echo $attempt->id."\n";
