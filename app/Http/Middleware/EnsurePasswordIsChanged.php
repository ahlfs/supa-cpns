<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only thing keeping a temporary password alive: without mail there is no
 * reset link to expire, so the account is held at the password form until it is
 * changed. The password page and logout stay reachable, or the user would be
 * trapped in a loop.
 *
 * Two shapes, because two clients ask. A page a browser navigates to gets the
 * redirect it can follow. A request that expects JSON — the exam room's answer and
 * position saves, which `resources/js/modules/exam.js` sends with
 * `Accept: application/json` and reads `error.data.message` from — gets a
 * machine-readable refusal instead: a 302 there is followed silently by fetch(),
 * the body is HTML, the module reads nothing, and a participant whose password an
 * administrator reset mid-sitting would save no answers and could not finish
 * before the clock ran out. 409 is the honest status: the request is fine, the
 * account's state is what refuses it.
 */
final class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password === true
            && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            if ($request->expectsJson()) {
                abort(409, 'Password akunmu baru direset, jadi jawaban tidak bisa disimpan sampai '
                    .'password barunya dipakai. Ganti passwordmu di halaman akun, lalu buka kembali ujian '
                    .'ini — waktunya masih berjalan.');
            }

            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
