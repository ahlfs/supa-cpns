<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

final class StorePositionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer'],
        ];
    }
}
