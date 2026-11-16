<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\QuestionCategory;
use App\Models\QuestionOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One item, two shapes.
 *
 * TWK/TIU: five options plus a single `key` letter. A score on one of their options
 * is refused outright, because a keyed item has no weights.
 *
 * TKP: five options with a weight each and no key at all — in TKP every option is
 * defensible and only the weight separates them.
 *
 * The rule the whole subtest rests on: a TKP item must carry the weights 1, 2, 3, 4
 * and 5 exactly once each. Two options sharing a weight makes the item worthless,
 * and it must never save.
 *
 * The validated payload leaves through `payload()` and NOT through `attributes()`:
 * `FormRequest::attributes()` is the framework's own hook for human-readable
 * attribute names, and it is called while the validator is still being built, so a
 * payload method wearing that name would be invoked before `$this->validator`
 * exists and read it as null.
 */
class StoreQuestionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->category();

        $rules = [
            'category' => ['required', Rule::enum(QuestionCategory::class)],
            'subcategory' => ['required', 'string', Rule::in(array_keys($category?->subcategories() ?? []))],
            'stem' => ['required', 'string', 'min:10'],
            'explanation' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'options' => ['required', 'array', 'size:'.count(QuestionOption::LETTERS)],
        ];

        foreach (QuestionOption::LETTERS as $letter) {
            $rules["options.{$letter}.body"] = ['required', 'string'];

            // The weight ceiling is the blueprint's per-item maximum, never a
            // literal: for TKP the option weight is the points the item awards.
            $rules["options.{$letter}.score"] = $category === QuestionCategory::Tkp
                ? ['required', 'integer', 'between:1,'.$category->perItemMaximum()]
                : ['prohibited'];
        }

        $rules['key'] = ($category === null || $category->requiresKey())
            ? ['required', Rule::in(QuestionOption::LETTERS)]
            : ['prohibited'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = $this->category();
            $options = $this->input('options');

            if ($category === null || ! is_array($options)) {
                return;
            }

            if ($options !== [] && collect(array_keys($options))->sort()->values()->all() !== QuestionOption::LETTERS) {
                $validator->errors()->add('options', 'Lima opsi harus berlabel A sampai E.');
            }

            if ($category !== QuestionCategory::Tkp) {
                return;
            }

            // The ladder is 1..ceiling, built from the same per-item maximum the
            // between rule uses, so the two can never disagree.
            $ladder = range(1, $category->perItemMaximum());

            $scores = collect($options)
                ->map(fn ($option): int => (int) ($option['score'] ?? 0))
                ->sort()
                ->values()
                ->all();

            if ($scores !== $ladder) {
                $readable = implode(', ', array_slice($ladder, 0, -1)).', dan '.$ladder[count($ladder) - 1];

                $validator->errors()->add(
                    'options',
                    "Bobot TKP harus tepat {$readable} — tidak boleh ada bobot kembar.",
                );
            }
        });
    }

    public function category(): ?QuestionCategory
    {
        return QuestionCategory::tryFrom((string) $this->input('category'));
    }

    /**
     * The item's own columns, ready for create() or fill().
     *
     * Named `payload()` rather than `attributes()`: the latter is a framework hook
     * on FormRequest (see the class docblock).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'category' => $this->validated('category'),
            'subcategory' => $this->validated('subcategory'),
            'stem' => $this->validated('stem'),
            'explanation' => $this->validated('explanation'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
