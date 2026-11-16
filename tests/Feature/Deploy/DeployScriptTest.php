<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use Tests\TestCase;

/**
 * `deploy/host-cpns.sh` is code, and the failure it guards against is invisible to
 * its own smoke test: `enable` fetches only `/`, which renders from
 * `resources/content` and never touches the database. A deploy that skipped the
 * module's migrations would therefore print "0 failed" while every page that reads
 * a table — /masuk, /daftar, /tryout and /admin — answered 500 on a missing table,
 * and the documented `php artisan cpns:admin` could not create the first
 * administrator either.
 *
 * These assertions read the script instead of running it: running it needs root,
 * system caches and the live host. What they pin is the ordering, which is the
 * whole of the fix — the migration runs as the project owner, inside the build
 * path, before the smoke test.
 */
final class DeployScriptTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(base_path('deploy/host-cpns.sh'));
    }

    /**
     * The body of a shell function: from its `name() {` line to the first
     * following line that is only `}`.
     */
    private function functionBody(string $name): string
    {
        $script = $this->script();
        $start = strpos($script, $name.'() {');

        $this->assertIsInt($start, "deploy/host-cpns.sh must define {$name}().");

        $end = strpos($script, "\n}\n", (int) $start);

        $this->assertIsInt($end, "{$name}() must be closed by a line holding only `}`.");

        return substr($script, (int) $start, (int) $end - (int) $start);
    }

    public function test_the_build_step_applies_the_database_migrations_as_the_project_owner(): void
    {
        $build = $this->functionBody('cmd_build');

        $this->assertStringContainsString('cmd_migrate', $build, 'The build path must run the migrations.');

        $migrate = $this->functionBody('cmd_migrate');

        // The owner-run helper, never a bare call: run as root, the migration would
        // leave a root-owned SQLite file the pool cannot write.
        $this->assertStringContainsString('as_owner', $migrate, 'The migration must run through the owner-run helper.');
        $this->assertStringContainsString('php artisan migrate --force', $migrate);

        // A failed migration has to stop the deploy rather than be printed and
        // stepped over.
        $this->assertStringContainsString('die', $migrate, 'A failed migration must stop the deploy.');
    }

    public function test_the_migration_runs_before_the_smoke_test_and_before_the_caches(): void
    {
        $build = $this->functionBody('cmd_build');

        // Inside the build: the schema exists before `artisan optimize` bakes the
        // config that points at it.
        $this->assertLessThan(
            strpos($build, 'artisan optimize'),
            strpos($build, 'cmd_migrate'),
            'The migration must run before artisan optimize.',
        );

        // And the whole of it before the smoke test, which is what makes a failed
        // migration fail the deploy instead of passing it.
        $enable = $this->functionBody('cmd_enable');

        $this->assertLessThan(
            strpos($enable, 'cmd_smoke'),
            strpos($enable, 'cmd_build'),
            'The build (and its migration) must run before the smoke test.',
        );
    }

    public function test_the_step_is_reachable_on_its_own_and_reported_by_status(): void
    {
        $script = $this->script();

        // Runnable by itself, so an operator can apply a migration without a deploy.
        $this->assertMatchesRegularExpression('/^\s*migrate\) cmd_migrate ;;/m', $script);

        $this->assertStringContainsString('migrate  php artisan migrate --force', $script, 'usage() names the migrate step.');
        $this->assertStringContainsString('migrations    :', $script, 'status reports pending migrations.');
    }

    /**
     * `.env` is gitignored, so the host's own file is the only copy of these and
     * nothing in the repository can state them. They were printed by `status` and
     * asserted nowhere, which is a deploy that looks fine and is not — an English
     * locale, a debug environment in the config cache, a database the pool cannot
     * write, a session that dies mid-sitting. Each one now goes through `bad()`,
     * whose count is what makes the run exit non-zero.
     */
    public function test_status_asserts_the_requirements_only_the_host_can_supply(): void
    {
        $status = $this->functionBody('cmd_status');
        $check = $this->functionBody('check_env_requirements');

        // Reached from status, not merely defined.
        $this->assertStringContainsString('check_env_requirements', $status);

        foreach (['APP_LOCALE', 'APP_ENV', 'APP_DEBUG', 'APP_KEY', 'DB_CONNECTION', 'DB_DATABASE', 'SESSION_LIFETIME'] as $key) {
            $this->assertStringContainsString($key, $check, "status must assert {$key}.");
        }

        // A wrong value has to be a failure, not a line of output.
        $this->assertStringContainsString('bad ', $check, 'A wrong value must increment the failure count.');
        $this->assertStringContainsString('as_owner', $check, 'The database file must be checked as the owner the migrations run as.');

        // The sitting length comes from the blueprint, never a literal here.
        $this->assertStringContainsString('config/cpns.php', $this->functionBody('sitting_minutes'));

        // And the requirements are stated together for the operator.
        $notes = (string) file_get_contents(base_path('docs/WORKING-NOTES.md'));

        foreach (['APP_LOCALE', 'APP_ENV', 'APP_DEBUG', 'APP_KEY', 'DB_CONNECTION', 'DB_DATABASE', 'SESSION_LIFETIME'] as $key) {
            $this->assertStringContainsString($key, $notes, "docs/WORKING-NOTES.md must state {$key}.");
        }
    }
}
