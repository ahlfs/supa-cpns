<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAnswerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer'],
            // Null is how "skip this item" travels, so it is allowed and meaningful.
            'selected_option_id' => ['nullable', 'integer'],
            // Absent means "the caller said nothing about the flag", which is not
            // the same as false: clicking an option must not clear a review mark.
            'is_flagged' => ['sometimes', 'boolean'],
        ];
    }
}
