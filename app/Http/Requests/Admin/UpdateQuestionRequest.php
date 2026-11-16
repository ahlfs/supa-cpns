<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Question;
use Illuminate\Validation\Rule;

/**
 * Editing an item is always allowed, including one that is already used in a
 * package that has been sat — the results page reads text live from the bank, so a
 * typo fix reaches past participants. What the rule protects is the score, which is
 * frozen on the answer rows and never moves.
 *
 * One field is not editable, for the same reason: the category IS the grading
 * shape. TWK and TIU grade on a single key, TKP grades on the 1-5 weight ladder —
 * `syncOptions` rewrites `is_correct` and `score` from whichever shape it is
 * handed. So a crafted PUT that keeps every other field and swaps `category` would
 * re-grade the item inside a package participants are sitting, silently changing
 * what the composition means (31 TWK / 44 TKP) while the sitting keeps serving.
 * The form's hidden field is not a boundary, so the refusal lives here: the
 * submitted category must be the stored one. Every other field is unchanged from
 * `StoreQuestionRequest`.
 */
final class UpdateQuestionRequest extends StoreQuestionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $question = $this->route('question');

        // The bound model is what the row says, never the request. A `category`
        // that is merely repeated back is accepted, so the edit form keeps working.
        $rules['category'] = ['required', Rule::in([
            $question instanceof Question ? $question->category->value : '',
        ])];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $question = $this->route('question');
        $label = $question instanceof Question ? $question->category->label() : '';

        return [
            'category.in' => 'Subtes sebuah soal tidak bisa diubah: subtes itu bentuk penilaiannya '
                .'(TWK dan TIU dinilai dari satu kunci, TKP dari bobot tiap opsi), jadi mengubahnya '
                .'akan menilai ulang soal ini di paket yang sedang dikerjakan peserta. Soal ini '
                .$label.'. Kalau memang perlu subtes lain, buat soal baru di bank.',
        ];
    }
}
