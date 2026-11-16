<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Either a pasted block or an uploaded file, never neither.
 *
 * Both paths end in the same string, because everything downstream — the parser,
 * the preview and the commit — works on text and nothing else.
 */
final class ImportQuestionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'payload' => ['required_without:file', 'nullable', 'string'],
            'file' => ['required_without:payload', 'nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * The uploaded file wins when both are present, because that is what the user
     * most recently chose to do.
     */
    public function payload(): string
    {
        if ($this->hasFile('file')) {
            return (string) file_get_contents((string) $this->file('file')?->getRealPath());
        }

        return (string) $this->input('payload');
    }
}
