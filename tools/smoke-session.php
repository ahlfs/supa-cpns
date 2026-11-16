<?php

/*
 * A signed-in browser session for the smoke tests, without a password.
 *
 * tools/question-chat-smoke.mjs drives a page that sits behind the login gate, so
 * it needs a session. Handing a script somebody's email and password to get one is
 * the wrong trade: credentials end up in environment variables, in shell history
 * and in logs, for a check that has nothing to do with logging in. Credentials are
 * also the one thing an agent must never be given.
 *
 * So this borrows a session instead of creating one. It asks the application for a
 * cookie exactly as a first-time visitor would, then rewrites the row that cookie
 * points at so it carries the login. Everything about the cookie — the value
 * prefix, the encryption, the serialization flag — is the application's own work,
 * because guessing at any of those produces a cookie that fails in a way which
 * looks precisely like "the page is broken".
 *
 * WHAT THIS IS: shell access to this host plus the application key, which is what
 * running artisan already means, and that is already enough to read .env and open
 * the database. It adds no reachable surface — there is no route here, and nothing
 * over the network can call it.
 *
 * Usage, from the repository root, on the host that serves the site:
 *
 *   CHAT_SMOKE_COOKIE="$(php artisan tinker --execute="require 'tools/smoke-session.php';" | tail -1)" \
 *     node tools/question-chat-smoke.mjs https://cpns.buatjalan.xyz 12
 *
 * SMOKE_EMAIL picks the account (default chat-smoke@example.test); SMOKE_BASE
 * picks the site (default APP_URL). Give it a throwaway: the smoke writes real
 * rows and spends real tokens. Delete the account when you are done.
 */

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$base = rtrim((string) (getenv('SMOKE_BASE') ?: config('app.url')), '/');
$email = (string) (getenv('SMOKE_EMAIL') ?: 'chat-smoke@example.test');
$name = (string) config('session.cookie');

// A visitor's cookie, from the application itself.
$response = Http::get($base.'/masuk');

if ($response->failed()) {
    fwrite(STDERR, "Could not reach {$base}/masuk\n");

    return;
}

$raw = null;

foreach ($response->cookies() as $cookie) {
    if ($cookie->getName() === $name) {
        // The cookie jar escapes the base64 padding; the header does not.
        $raw = urldecode((string) $cookie->getValue());
    }
}

if ($raw === null || $raw === '') {
    fwrite(STDERR, "No {$name} cookie came back from {$base}/masuk\n");

    return;
}

$id = CookieValuePrefix::validate(
    $name,
    Crypt::decrypt($raw, EncryptCookies::serialized($name)),
    Crypt::getAllKeys(),
);

if ($id === null) {
    fwrite(STDERR, "The cookie did not carry the application's own prefix\n");

    return;
}

$user = User::query()->where('email', $email)->first();

if ($user === null) {
    fwrite(STDERR, "No account for {$email}\n");

    return;
}

/*
 * The payload is base64 of a JSON object. It is not `serialize()`, which is what
 * the shape suggests and what produces a session that reads back empty — the
 * failure mode that cost the most time here. The rows the application writes
 * settle it, so this asks one of them rather than guessing: decode it and the
 * first characters are `{"_token"`.
 */
DB::table('sessions')->updateOrInsert(
    ['id' => $id],
    [
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'question-chat-smoke',
        'payload' => base64_encode((string) json_encode([
            Auth::guard('web')->getName() => $user->id,
            // The page prints its CSRF token out of this session, so the token it
            // prints and the one it checks are the same by construction.
            '_token' => Str::random(40),
        ])),
        'last_activity' => time(),
    ],
);

echo $raw."\n";
