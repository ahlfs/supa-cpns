<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function create(ContentRepository $content): View
    {
        return view('auth.login', ['site' => $content->site()] + $content->chrome());
    }

    public function store(Request $request): RedirectResponse
    {
        // Normalised to lowercase before validation: mobile keyboards capitalise
        // the first letter, and the registration path stores the lowercase form,
        // so a participant who registered "Budi@Example.com" must be able to sign
        // in as "budi@example.com".
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Throttled per email+IP rather than per IP alone, so one shared connection
        // cannot lock out the whole house.
        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();
        $limit = (int) config('cpns.password.login_attempts_per_minute');

        if (RateLimiter::tooManyAttempts($throttleKey, $limit)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    .RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak cocok.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
