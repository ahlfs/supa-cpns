<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionCategory;
use App\Http\Controllers\Controller;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Support\ContentRepository;
use App\Support\PackageComposition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Composing a package by hand, one item at a time.
 *
 * Once anyone has sat a package its composition is frozen: two sittings of
 * different questions are not comparable on the history chart. The items
 * themselves stay editable from the bank, so a typo is still fixable — it is only
 * the list and its order that lock.
 *
 * Every refusal here is a ValidationException, never a bare abort: an
 * administrator working through normal controls must stay on the page with an
 * explanation, not land on an error page.
 */
final class PackageBuilderController extends Controller
{
    public function show(ExamPackage $package, ContentRepository $content): View
    {
        // Read defensively: a hand-typed `?q[]=x` or `?category[]=x` gives the
        // controller an array where it expects a string, and casting that is a 500
        // for an administrator who mistyped a URL. A term that is not a scalar is no
        // term at all.
        $categoryTerm = request()->query('category');
        $category = QuestionCategory::tryFrom(is_scalar($categoryTerm) ? (string) $categoryTerm : '');

        $term = request()->query('q');
        $search = is_scalar($term) ? (string) $term : '';

        $taken = $package->questions()->pluck('questions.id');

        return view('admin.packages.builder', [
            'site' => $content->site(),
            'title' => 'Susun '.$package->title,
            'package' => $package,
            'report' => PackageComposition::report($package),
            'complete' => PackageComposition::isComplete($package),
            'shortfall' => PackageComposition::shortfallMessage($package),
            'locked' => $package->isLocked(),
            'items' => $package->questions()->with('options')->get(),
            'candidates' => Question::query()
                ->active()
                ->with('options')
                ->when($category !== null, fn ($query) => $query->category($category))
                ->when($search !== '', fn ($query) => $query->where('stem', 'like', '%'.$search.'%'))
                ->whereNotIn('questions.id', $taken)
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'category' => $category,
            'search' => $search,
        ]);
    }

    public function attach(Request $request, ExamPackage $package): RedirectResponse
    {
        $this->refuseIfLocked($package);

        // The candidate list is filtered through Question::active(), so a retired
        // item is invisible in the UI — but the pivot is read with no such filter on
        // the participant side, and PackageComposition counts the row either way.
        // The rule therefore lives here too, not only in the query that feeds the
        // picker.
        $request->validate([
            'question_id' => [
                'required',
                'integer',
                Rule::exists('questions', 'id')->where('is_active', true),
            ],
        ]);

        $question = Question::query()->findOrFail($request->integer('question_id'));

        // Attaching twice is a no-op rather than an error: the unique key on
        // (package, question) is the rule, and a double click is not a mistake
        // worth a red message.
        if (! $this->belongsToPackage($package, $question)) {
            $package->addQuestion($question);
        }

        return back()->with('status', 'Soal ditambahkan ke paket.');
    }

    public function detach(ExamPackage $package, Question $question): RedirectResponse
    {
        $this->refuseIfLocked($package);

        if (! $this->belongsToPackage($package, $question)) {
            throw ValidationException::withMessages([
                'package' => 'Soal ini tidak ada di dalam paket, jadi tidak ada yang dikeluarkan.',
            ]);
        }

        $package->questions()->detach($question->id);

        return back()->with('status', 'Soal dikeluarkan dari paket.');
    }

    public function move(Request $request, ExamPackage $package, Question $question): RedirectResponse
    {
        $this->refuseIfLocked($package);

        $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        // A URL naming a question outside this package would otherwise fall through
        // the swap below and answer with a silent redirect, as if the move had
        // happened. Say what is actually true instead.
        if (! $this->belongsToPackage($package, $question)) {
            throw ValidationException::withMessages([
                'package' => 'Soal ini tidak ada di dalam paket, jadi urutannya tidak bisa dipindah.',
            ]);
        }

        $direction = $request->string('direction')->toString() === 'up' ? -1 : 1;

        DB::transaction(function () use ($package, $question, $direction): void {
            $ordered = $package->questions()->get()->all();
            $index = null;

            foreach ($ordered as $position => $row) {
                if ($row->id === $question->id) {
                    $index = $position;
                    break;
                }
            }

            $target = $index === null ? null : $index + $direction;

            // The bounds are the real no-op: moving the first item up, or the last
            // one down, changes nothing and says nothing, which is correct. The
            // `$index === null` arm can no longer be reached by a crafted URL — the
            // membership check above answers that out loud — so it only guards a
            // concurrent detach between that check and this transaction.
            if ($index === null || $target === null || $target < 0 || $target >= count($ordered)) {
                return;
            }

            // A straight swap collides with the unique key on (package, position),
            // so every row is pushed out of range first and renumbered after.
            // Both statements are in this transaction, so the shifted state is
            // never visible to another reader.
            $package->questions()->newPivotStatement()
                ->where('exam_package_id', $package->id)
                ->update(['position' => DB::raw('position + 1000')]);

            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

            foreach ($ordered as $offset => $row) {
                $package->questions()->updateExistingPivot($row->id, ['position' => $offset + 1]);
            }
        });

        return back();
    }

    public function publish(ExamPackage $package): RedirectResponse
    {
        // Refused in the controller, not only by disabling a button: a half-built
        // package must never be reachable by a participant, and a disabled button
        // is a hint, not a rule.
        if (! PackageComposition::isComplete($package)) {
            throw ValidationException::withMessages([
                'package' => 'Paket belum genap. '.PackageComposition::shortfallMessage($package),
            ]);
        }

        $package->forceFill(['status' => ExamPackage::STATUS_PUBLISHED])->save();

        return back()->with('status', 'Paket dibuka. Peserta bisa mengerjakannya sekarang.');
    }

    public function unpublish(ExamPackage $package): RedirectResponse
    {
        $package->forceFill(['status' => ExamPackage::STATUS_DRAFT])->save();

        return back()->with('status', 'Paket ditutup lagi.');
    }

    /**
     * The lock, in one place, for all three composition actions.
     *
     * `isLocked()` is true as soon as any attempt row exists, in any status, so a
     * participant who has sat the package freezes the list — and the way out is
     * named in the message, because an admin hitting this needs to know that the
     * text is still fixable and that a different order means a new package.
     */
    private function refuseIfLocked(ExamPackage $package): void
    {
        if ($package->isLocked()) {
            throw ValidationException::withMessages([
                'package' => 'Komposisi paket ini dikunci karena sudah ada yang mengerjakannya. '
                    .'Teks soal tetap bisa diubah dari bank soal; untuk komposisi berbeda, buat paket baru.',
            ]);
        }
    }

    /**
     * Whether the item is actually part of this package's list.
     *
     * `detach` and `move` take the question from the URL, so a hand-made request can
     * name one that belongs to another package or to none. Nothing would be written
     * either way — but both actions end in a banner, and a banner about a change
     * that did not happen is a lie the admin has to disprove by hand.
     */
    private function belongsToPackage(ExamPackage $package, Question $question): bool
    {
        return $package->questions()->whereKey($question->id)->exists();
    }
}
