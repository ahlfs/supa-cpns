<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A package's own columns.
 *
 * The items are deliberately not part of this payload: composition happens one
 * item at a time in the builder, where the shortfall is visible while the choice
 * is made, so there is no bulk field here that a single form could get wrong.
 *
 * The validated payload leaves through `payload()` and NOT through
 * `attributes()`: that name is the framework's own hook for human-readable
 * attribute names and is called while the validator is still being built, so a
 * payload method wearing it would be invoked before `$this->validator` exists
 * (the same rule StoreQuestionRequest documents).
 */
final class BuildPackageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('exam_packages', 'slug')->ignore($this->route('package')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => [
                'required', 'integer',
                'between:'.(int) config('cpns.duration_minutes_min').','.(int) config('cpns.duration_minutes_max'),
            ],
        ];
    }

    /**
     * An empty slug is not an error, it is the instruction "make one from the
     * title" — the admin should not have to type a URL by hand.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
        ]);
    }

    /**
     * The package's columns, ready for create() or update().
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'title' => $this->validated('title'),
            'slug' => $this->validated('slug'),
            'description' => $this->validated('description'),
            'duration_minutes' => (int) $this->validated('duration_minutes'),
        ];
    }
}
