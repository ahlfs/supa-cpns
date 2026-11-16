<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportQuestionsRequest;
use App\Models\Question;
use App\Support\ContentRepository;
use App\Support\QuestionParser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * All-or-nothing on purpose. A half-finished import into a live bank is far worse
 * than a refused one: a broken item is only discovered when a participant meets it,
 * and by then somebody's sitting is already spoiled.
 */
final class ImportController extends Controller
{
    public function index(ContentRepository $content): View
    {
        return view('admin.import.index', [
            'site' => $content->site(),
            'title' => 'Impor soal',
            'rows' => null,
            'importErrors' => [],
            'payload' => '',
        ]);
    }

    public function preview(ImportQuestionsRequest $request, ContentRepository $content, QuestionParser $parser): View
    {
        $payload = $request->payload();
        $parsed = $parser->parse($payload);

        return view('admin.import.index', [
            'site' => $content->site(),
            'title' => 'Impor soal',
            'rows' => $parsed['rows'],
            'importErrors' => $parsed['errors'],
            'payload' => $payload,
        ]);
    }

    public function commit(ImportQuestionsRequest $request, QuestionParser $parser): RedirectResponse
    {
        $payload = $request->payload();
        $parsed = $parser->parse($payload);

        // Nothing is written unless the preview would have been clean too, so a
        // hand-crafted POST cannot skip the check.
        if ($parsed['errors'] !== []) {
            return back()->withErrors(['payload' => 'Masih ada baris yang salah. Perbaiki dulu, tidak ada yang diimpor.']);
        }

        if ($parsed['rows'] === []) {
            return back()->withErrors(['payload' => 'Tidak ada baris yang bisa diimpor.']);
        }

        DB::transaction(function () use ($parsed): void {
            foreach ($parsed['rows'] as $row) {
                $question = Question::create([
                    'category' => $row['category'],
                    'subcategory' => $row['subcategory'],
                    'stem' => $row['stem'],
                    'explanation' => $row['explanation'],
                    'is_active' => $row['is_active'],
                ]);

                // The same call the question form makes, so an imported item cannot
                // end up graded differently from a typed one.
                $question->syncOptions($row['options'], $row['key'] ?? null);
            }
        });

        return redirect()
            ->route('admin.import.index')
            ->with('status', count($parsed['rows']).' soal berhasil diimpor.');
    }
}
