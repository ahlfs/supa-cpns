<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The shape of the account pages themselves.
 *
 * These guard the two things a reader notices without reading the code: that
 * the page is not hidden behind the fixed header, and that a rejected form says
 * everything it has to say at once.
 */
final class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The header is `fixed`, so it reserves no space: every page clears it
     * itself. The offset lives in the shared shell, and the account pages have
     * to be the pages that use it — a page without it renders under the pill.
     */
    public function test_the_account_pages_reserve_space_for_the_fixed_header(): void
    {
        foreach ([route('login'), route('register')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('pt-[96px]', escape: false)
                ->assertSee('lg:pt-[112px]', escape: false);
        }
    }

    /**
     * A rejected sign-in can report both fields at once. Showing only the first
     * message makes the visitor resubmit to discover the second, so the alert
     * has to carry every message the validator produced.
     */
    public function test_a_rejected_form_lists_every_message_it_produced(): void
    {
        $this->from(route('login'));

        $this->post(route('login'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password']);

        // Asked a second time so the rendered page can be read without the
        // session assertion consuming the flashed errors first.
        $html = $this->followingRedirects()->post(route('login'), [])->getContent();

        $this->assertStringContainsString('role="alert"', $html, 'The rejected form rendered no alert.');

        $alert = Str::between($html, 'role="alert"', '</div>');

        $this->assertSame(2, substr_count($alert, '<li>'), 'The alert rendered fewer messages than the validator produced.');
    }

    /**
     * Colour alone is not an error signal: the field carries the state for
     * assistive technology too, and the focus ring keeps the error colour so a
     * focused field does not read as two conflicting states.
     */
    public function test_a_rejected_field_carries_the_error_state_in_its_markup(): void
    {
        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login'), [])
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('focus:outline-offset-0 focus:outline-danger', escape: false);
    }
}
