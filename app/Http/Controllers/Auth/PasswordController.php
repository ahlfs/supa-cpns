<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class PasswordController extends Controller
{
    public function edit(Request $request, ContentRepository $content): View
    {
        return view('auth.password', [
            'site' => $content->site(),
            'forced' => $request->user()->must_change_password,
        ] + $content->chrome());
    }

    public function update(Request $request): RedirectResponse
    {
        $minLength = (int) config('cpns.password.min_length');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min($minLength)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak cocok.']);
        }

        $request->user()->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Password sudah diganti.');
    }
}
