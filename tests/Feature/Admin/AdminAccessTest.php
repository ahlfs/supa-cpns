<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    /**
     * Every route in the admin group, not just `/admin`.
     *
     * The name claimed a sweep that did not exist: the test hit one URL. The
     * middleware is attached where `routes/admin.php` is mounted, so the sweep is
     * what proves a screen cannot be added without it.
     *
     * A data provider would be the tidier shape, but providers run before the
     * application is booted and the route table does not exist yet, so the sweep is
     * a loop over `Route::getRoutes()`. Each `{parameter}` has to be bound to a real
     * row, because SubstituteBindings runs inside the `web` group — ahead of
     * `admin` — and an unresolvable binding answers 404 before the guard is ever
     * reached.
     */
    public function test_a_peserta_gets_a_forbidden_page_on_every_admin_route(): void
    {
        $peserta = User::factory()->create();

        $bound = [
            'question' => (string) Question::factory()->create()->id,
            'package' => ExamPackage::factory()->create()->slug,
            'user' => (string) User::factory()->create()->id,
        ];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'admin.'));

        // A guard against a filter that silently matches nothing: the panel has
        // more than fifteen screens.
        $this->assertGreaterThan(15, $routes->count(), 'The sweep must see the whole admin panel.');

        foreach ($routes as $route) {
            $uri = preg_replace_callback(
                '/\{(\w+)\??\}/',
                fn (array $match): string => $bound[$match[1]] ?? '1',
                $route->uri(),
            );

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $response = $this->actingAs($peserta)->call($method, '/'.$uri);

                $this->assertSame(
                    403,
                    $response->getStatusCode(),
                    "{$method} /{$uri} ({$route->getName()}) must be forbidden for a peserta.",
                );
            }
        }
    }

    public function test_an_admin_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ringkasan');
    }

    public function test_the_dashboard_lists_packages_that_are_not_yet_complete(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout Setengah Jadi']);

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin');

        $response->assertOk();
        $response->assertSee('Tryout Setengah Jadi');
        $response->assertSee('TWK kurang 30');
    }

    public function test_a_temporary_password_still_blocks_the_admin_panel(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('password.edit'));
    }

    /**
     * Being an administrator must be visible, not merely permitted.
     *
     * The panel is deliberately absent from the participant navigation, so before
     * this the only way in was to type the URL: the role was real, and the account
     * looked exactly like a participant's. These three assertions are what stop
     * that from coming back.
     */
    public function test_an_administrator_is_offered_the_panel_in_the_participant_chrome(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-header-account="admin"', $html);
        $this->assertStringContainsString(route('admin.dashboard', absolute: false), $html);
        $this->assertStringContainsString('Buka panel', $html);
    }

    public function test_a_peserta_is_offered_no_panel_link(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-header-account="admin"', $html);
        $this->assertStringNotContainsString(route('admin.dashboard', absolute: false), $html);
        $this->assertStringNotContainsString('Buka panel', $html);
    }

    public function test_the_panel_offers_a_way_back_to_the_participant_site(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Lihat situs peserta')
            ->assertSee(route('dashboard', absolute: false));
    }
}
