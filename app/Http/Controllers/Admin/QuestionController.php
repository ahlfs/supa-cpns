<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class QuestionController extends Controller
{
    public function index(Request $request, ContentRepository $content): View
    {
        // Read the way the accounts list reads it, so the two screens behave the same —
        // except that a query string can carry anything: `?q[]=x`, `?category[]=x` and
        // `?subcategory[]=x` hand the controller an array where it expects a string, and
        // casting that is a 500 for an administrator who mistyped a URL. A term that is
        // not a scalar is no term at all.
        $categoryTerm = $request->query('category');
        $category = QuestionCategory::tryFrom(is_scalar($categoryTerm) ? (string) $categoryTerm : '');

        $term = $request->query('q');
        $search = is_scalar($term) ? (string) $term : '';

        $subcategoryTerm = $request->query('subcategory');
        $subcategory = is_scalar($subcategoryTerm) ? (string) $subcategoryTerm : '';

        $questions = Question::query()
            ->with(['options', 'packages:id,title'])
            ->when($category !== null, fn ($query) => $query->category($category))
            ->when($subcategory !== '', fn ($query) => $query->where('subcategory', $subcategory))
            ->when($search !== '', fn ($query) => $query->where('stem', 'like', '%'.$search.'%'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.questions.index', [
            'site' => $content->site(),
            'title' => 'Bank soal',
            'questions' => $questions,
            'category' => $category,
            'search' => $search,
            'subcategory' => $subcategory,
        ]);
    }

    public function create(ContentRepository $content): View
    {
        // The category is chosen before the form is drawn, so the form never has to
        // guess which of its two faces is the real one, and no field is ever
        // submitted that the rules would refuse. Read defensively for the same reason
        // as the list above: a hand-typed `?category[]=x` is a bad URL, not a crash.
        $categoryTerm = request()->query('category');
        $category = QuestionCategory::tryFrom(is_scalar($categoryTerm) ? (string) $categoryTerm : '');

        return view('admin.questions.create', [
            'site' => $content->site(),
            'title' => 'Tambah soal',
            'category' => $category,
        ]);
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $question = Question::create($request->payload());

        $this->writeOptions($question, $request);

        return redirect()
            ->route('admin.questions.index', ['category' => $question->category->value])
            ->with('status', 'Soal tersimpan.');
    }

    public function edit(Question $question, ContentRepository $content): View
    {
        $question->load('options');

        return view('admin.questions.edit', [
            'site' => $content->site(),
            'title' => 'Ubah soal',
            'question' => $question,
            'category' => $question->category,
        ]);
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $question->update($request->payload());

        $this->writeOptions($question, $request);

        return redirect()
            ->route('admin.questions.index', ['category' => $question->category->value])
            ->with('status', 'Perubahan tersimpan. Skor peserta yang sudah selesai tidak berubah.');
    }

    /**
     * Deleting an item is the one operation that can damage history, and the
     * database is the last line of defence, not the first: both foreign keys into
     * `questions` are restrictOnDelete, so a bare delete() would surface as a 500
     * on a question that is already in a package or has already been answered.
     *
     * Both refusals are therefore checked here, before the delete, and answered in
     * Indonesian with the way out named.
     */
    public function destroy(Question $question): RedirectResponse
    {
        if ($question->packages()->exists()) {
            $packageCount = $question->packages()->count();

            return back()->with(
                'status',
                "Soal ini masih dipakai di {$packageCount} paket, jadi belum bisa dihapus. "
                .'Keluarkan dulu soal ini dari paket tersebut — komposisi paket yang sudah dikerjakan '
                .'peserta memang terkunci, jadi paket draf adalah jalan yang tersedia.',
            );
        }

        if (ExamAttemptAnswer::query()->where('question_id', $question->id)->exists()) {
            return back()->with(
                'status',
                'Soal ini sudah dijawab peserta, jadi riwayatnya harus tetap tersimpan dan barisnya '
                .'tidak boleh hilang. Perbaiki teksnya lewat halaman Ubah — skor percobaan yang sudah '
                .'selesai tidak akan berubah.',
            );
        }

        $question->delete();

        return back()->with('status', 'Soal dihapus dari bank.');
    }

    /**
     * Hands the form's validated options to the model, which owns the grading
     * shape. Only the unwrapping lives here: whether an item is keyed or weighted,
     * and what that means for `is_correct` and `score`, is decided in one place
     * (`Question::syncOptions`) that the bulk importer calls too.
     */
    private function writeOptions(Question $question, StoreQuestionRequest $request): void
    {
        $key = $request->validated('key');

        $question->syncOptions((array) $request->validated('options'), is_string($key) ? $key : null);
    }
}
