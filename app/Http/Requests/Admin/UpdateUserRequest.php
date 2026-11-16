<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The three columns an administrator may change about an account, and nothing else.
 *
 * `participant_number`, `password`, `must_change_password` and `remember_token` are
 * deliberately absent: the numbers are derived, and a password is only ever replaced
 * by the reset action, never as a side effect of an edit. The controller writes
 * `validated()`, so this list is the whole of what a request can reach.
 */
final class UpdateUserRequest extends FormRequest
{
    /**
     * Lowercased and trimmed exactly as login and registration normalise an address:
     * a typo corrected here and an address typed at the login form have to be one
     * spelling, or the correction produces an account nobody can sign in to.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // There is no mailer, so a mistyped address cannot be proven wrong by
            // sending to it; the administrator fixing it by hand is the whole
            // mitigation. What the rule can still promise is that the address is well
            // formed, and that two accounts never share one spelling.
            'email' => [
                'required', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            // Required, so an edit that forgets the field is refused instead of
            // quietly leaving — or moving — the role.
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
