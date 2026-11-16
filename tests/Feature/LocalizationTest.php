<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The module's own rule is that every string a user reads is Indonesian, and a
 * validation error is a string a user reads — the sentence *and* the field name
 * inside it. This proves the locale, the shipped translation file and its
 * `attributes` map actually reach a rendered form: a failure here means
 * `APP_LOCALE` drifted back to English, `lang/id/validation.php` went missing, or
 * its attribute names were emptied again — the three ways the old English
 * "The name field is required." or the half-translated "name wajib diisi" come
 * back.
 */
final class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_validation_error_is_rendered_in_indonesian_with_an_indonesian_field_name(): void
    {
        // Registration is a real, unauthenticated form: the empty name fails
        // `required`, and the page it redirects back to prints `$errors->first()`.
        $response = $this->followingRedirects()
            ->from('/daftar')
            ->post('/daftar', [
                'name' => '',
                'email' => 'bukan-surel',
                'password' => 'x',
            ]);

        $response->assertOk();

        // The Indonesian sentence carrying the field name the view prints above
        // the input — not the key the rules were written with. Under an input
        // labelled "Nama lengkap", "name" is the one word a participant would not
        // recognise on an otherwise Indonesian page.
        $response->assertSee('Nama lengkap wajib diisi');

        // And the raw key must not come back: this half fails if the `attributes`
        // map is emptied again, while the assertion above fails if the locale
        // drifts back to `en` or the translation file goes missing. The fix is
        // pinned from both sides.
        $response->assertDontSee('name wajib diisi');

        // None of the English defaults may be left anywhere on the page.
        $response->assertDontSee('The name field is required');
        $response->assertDontSee('is required');
    }

    public function test_the_change_password_form_names_its_current_password_field(): void
    {
        // The other auth form with a field name of its own. Only `current_password`
        // is left empty, so `$errors->first()` on that page is unambiguously its
        // message rather than the new password's.
        $response = $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->from('/akun/password')
            ->put('/akun/password', [
                'current_password' => '',
                'password' => 'rahasia123',
            ]);

        $response->assertOk();
        $response->assertSee('Password saat ini wajib diisi');
        $response->assertDontSee('current password wajib diisi');
    }

    public function test_the_admin_question_form_names_its_columns(): void
    {
        // The leak reached the admin forms too: `stem`, `subcategory` and `role`
        // are column names, never words an administrator sees. The question form
        // renders `$errors->all()`, so the renamed messages are all on the page.
        $response = $this->actingAs(User::factory()->admin()->create())
            ->followingRedirects()
            ->from('/admin/soal/baru?category=twk')
            ->post('/admin/soal', [
                'category' => 'twk',
                'subcategory' => 'pilar_negara',
                'stem' => '',
                'explanation' => 'Penjelasan singkat.',
                'is_active' => '1',
                'key' => 'C',
                'options' => [
                    'A' => ['body' => 'Opsi A'],
                    'B' => ['body' => 'Opsi B'],
                    'C' => ['body' => 'Opsi C'],
                    'D' => ['body' => 'Opsi D'],
                    'E' => ['body' => 'Opsi E'],
                ],
            ]);

        $response->assertOk();
        $response->assertSee('Teks soal wajib diisi');
        $response->assertDontSee('stem wajib diisi');
    }

    public function test_the_indonesian_locale_is_the_configured_one(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
    }

    /**
     * The locale stopped at validation messages: the module's own admin lists are
     * paginated, and the paginator prints four words through `__('Showing')`,
     * `__('to')`, `__('of')` and `__('results')` — those are JSON translation keys,
     * not entries in a PHP file — plus `pagination.previous` / `pagination.next`,
     * which DO come from `lang/id/pagination.php`. So the fix is both files, and
     * the assertion is the sentence a participant would read.
     */
    public function test_a_paginated_admin_list_renders_its_paging_wording_in_indonesian(): void
    {
        // A paginated list is only paginated when there is more than one page: 25
        // items at the bank's 20 per page.
        Question::factory()->twk()->count(25)->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/soal');

        $response->assertOk();

        $text = preg_replace('/\s+/', ' ', strip_tags((string) $response->getContent()));

        // The whole sentence, with the real numbers: the words are printed around
        // their own <span>s, so only the stripped text proves they are in order.
        $this->assertStringContainsString('Menampilkan 1 sampai 20 dari 25 hasil', (string) $text);

        // The pager's own links, from lang/id/pagination.php.
        $response->assertSee('Berikutnya', escape: false);

        // None of the English defaults may come back on either half.
        $this->assertStringNotContainsString('Showing', (string) $text);
        $response->assertDontSee('Previous');
        $response->assertDontSee('results</span>');
    }

    /**
     * The same completeness guard the validation file gets: every string the
     * framework's paginator prints must exist in `lang/id.json`, or one of them
     * silently falls back to its English key on an Indonesian page.
     */
    public function test_the_indonesian_json_file_covers_every_string_the_paginator_prints(): void
    {
        $views = glob(base_path('vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php'));

        $this->assertNotEmpty($views, 'The framework ships the paginator views this test reads.');

        /** @var array<string, string> $indonesian */
        $indonesian = json_decode((string) file_get_contents(lang_path('id.json')), true, flags: JSON_THROW_ON_ERROR);

        $keys = [];

        foreach ($views as $view) {
            preg_match_all("/__\\('([^']+)'\\)/", (string) file_get_contents($view), $matches);

            // Namespaced keys (`pagination.previous`) come from the PHP files, not
            // from the JSON one; the bare words are the JSON keys.
            $keys = [...$keys, ...array_filter($matches[1], fn (string $key): bool => ! str_contains($key, '.'))];
        }

        $keys = array_values(array_unique($keys));

        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $indonesian, "lang/id.json is missing the paginator word `{$key}`.");
            $this->assertNotSame($key, $indonesian[$key], "lang/id.json leaves `{$key}` as its English self.");
        }

        // And the pager's two links come from lang/id/pagination.php.
        /** @var array<string, string> $links */
        $links = require lang_path('id/pagination.php');

        foreach (['previous', 'next'] as $key) {
            $this->assertArrayHasKey($key, $links, "lang/id/pagination.php is missing `{$key}`.");
        }
    }

    /**
     * A translation file that covers only the rules someone happened to exercise
     * is a stub that reads as complete until a rule nobody tested fires in
     * English. Every key the framework ships must exist here too.
     */
    public function test_the_indonesian_validation_file_covers_every_default_rule(): void
    {
        /** @var array<string, mixed> $english */
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

        /** @var array<string, mixed> $indonesian */
        $indonesian = require lang_path('id/validation.php');

        foreach (array_keys($english) as $key) {
            // `custom` carries the framework's placeholder example, not a message
            // the application ever renders.
            if ($key === 'custom') {
                continue;
            }

            $this->assertKeysCovered((array) $english[$key], (array) $indonesian[$key], (string) $key);
        }
    }

    /**
     * @param  array<string, mixed>  $english
     * @param  array<string, mixed>  $indonesian
     */
    private function assertKeysCovered(array $english, array $indonesian, string $path): void
    {
        foreach ($english as $key => $value) {
            $nested = $path.'.'.$key;

            $this->assertArrayHasKey($key, $indonesian, "lang/id/validation.php is missing `{$nested}`.");

            if (is_array($value) && $value !== []) {
                $this->assertIsArray($indonesian[$key], "lang/id/validation.php: `{$nested}` must be an array.");
                $this->assertKeysCovered($value, (array) $indonesian[$key], $nested);
            }
        }
    }
}
