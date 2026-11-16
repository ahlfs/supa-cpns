<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ContentRepository;
use Tests\TestCase;

/**
 * Guards the homepage's structure: the sections it must render, in order, and the
 * content pipeline behind them. These assertions are about the contract between
 * resources/content and the Blade sections, so breaking a content key fails here
 * rather than in production.
 */
final class HomepageTest extends TestCase
{
    /** Section roots, in page order. */
    private const SECTIONS = [
        'header',
        'hero',
        'stats',
        'why',
        'program-heading',
        'programs',
        'prefooter',
        'footer',
        'floating',
    ];

    public function test_it_renders_the_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('SUPA CPNS', escape: false);
    }

    public function test_it_renders_every_section_in_page_order(): void
    {
        $html = $this->get('/')->getContent();

        $previous = -1;

        foreach (self::SECTIONS as $section) {
            $position = strpos($html, 'data-section="'.$section.'"');

            $this->assertNotFalse($position, "Section [{$section}] is missing from the homepage.");
            $this->assertGreaterThan($previous, $position, "Section [{$section}] is out of order.");
            $previous = $position;
        }
    }

    public function test_it_leaves_no_unported_section_behind(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('TODO', $html, 'A section is still a placeholder.');
    }

    public function test_it_serves_every_referenced_local_asset(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/(?:src|href)="([^"]+\.(?:png|jpe?g|webp|avif|svg|css|js))"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'The page references no assets at all.');

        foreach (array_unique($matches[1]) as $url) {
            $path = public_path(parse_url($url, PHP_URL_PATH) ?: '');

            if (! str_starts_with(parse_url($url, PHP_URL_PATH) ?: '', '/build/')) {
                $this->assertFileExists($path, "Referenced asset is missing: {$url}");
            }
        }
    }

    public function test_it_hides_the_page_from_search_engines_until_launch(): void
    {
        config()->set('site.indexable', false);

        $this->get('/')->assertSee('noindex, nofollow', escape: false);
    }

    /**
     * The bar is down to the wordmark and the account cluster: the two mega menus
     * it used to carry duplicated destinations the page already offers.
     *
     * Asserted on the markup rather than on the words: "Tryout" is still a program
     * card action and a hero call to action, so asserting the label is gone would
     * fail for a reason that has nothing to do with the bar. The dropdown is what
     * went away, and `data-menu-trigger` / `data-menu-panel` is what says so.
     */
    public function test_the_nav_bar_renders_no_mega_menus(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('data-menu-trigger', $html);
        $this->assertStringNotContainsString('data-menu-panel', $html);
    }

    /**
     * Nothing in the bar offers the homepage as a link, because the mark already
     * is one — at every width, including the drawer whose hamburger sits under it.
     *
     * Matched on `>Beranda<` rather than on the word: the mark's accessible name
     * is "SUPA CPNS — Beranda", so a page-wide string search would fail on the
     * very affordance the item was removed in favour of. The pattern says "some
     * element's text is exactly Beranda", which is what a nav item is.
     */
    public function test_the_bar_offers_no_home_entry_beside_the_mark(): void
    {
        $header = $this->headerMarkup();

        $this->assertStringContainsString(
            'data-header-brand',
            $header,
            'The header lost the mark that is the way home.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/>Beranda</u',
            $header,
            'The bar still renders a Beranda entry beside the mark.',
        );
    }

    /** The rendered header, sliced out so the footer or a section cannot satisfy an assertion. */
    private function headerMarkup(): string
    {
        $html = $this->get('/')->getContent();

        $start = strpos($html, 'data-section="header"');

        $this->assertNotFalse($start, 'The homepage rendered no header.');

        $end = strpos($html, '</header>', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    /**
     * The hero's tagline is the page's only <h1>, and it used to sit inside the
     * capsule that has been removed. Deleting the wrapper is exactly the edit that
     * takes the heading with it, so the heading is asserted on its own.
     */
    public function test_the_hero_keeps_the_pages_only_h1(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'The homepage must carry exactly one <h1>.');

        preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $matches);

        $this->assertStringContainsString(
            e(app(ContentRepository::class)->site()['tagline']),
            $matches[1] ?? '',
            'The hero\'s kicker line must be the <h1>.',
        );
    }

    /**
     * The four props are the hero's only artwork now, so a dropped loop would leave
     * the panel empty. The retired product shots are asserted absent so a stray
     * reference cannot outlive the files.
     */
    public function test_the_hero_renders_its_floating_props_and_nothing_else(): void
    {
        $html = $this->get('/')->getContent();

        foreach (['idea', 'trophy', 'video-ui', 'podium'] as $prop) {
            $this->assertStringContainsString(
                "assets/img/floating/float-{$prop}.svg",
                $html,
                "The hero is missing its [{$prop}] prop.",
            );
        }

        $this->assertStringNotContainsString('assets/img/hero.svg', $html);
        $this->assertStringNotContainsString('assets/img/prefooter.svg', $html);
    }

    /**
     * The band's badge restated the note under its own button, so it is gone. The
     * heading and the action it exists for are not.
     */
    public function test_the_prefooter_band_carries_no_badge(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('GRATIS DICOBA', $html);
        $this->assertStringContainsString('Belum Tahu Seberapa Siap Kamu Menghadapi SKD?', $html);
        $this->assertStringContainsString('Daftar Gratis', $html);
    }

    /**
     * The footer is the brand mark and the legal bar, and nothing else.
     *
     * It carried four link columns (Situs, Akun, Tryout, Penilaian) until they
     * were removed. The wrapper's `data-footer-columns` hook went with them: it
     * described the columns and nothing ever read it, so leaving it behind would
     * be a marker pointing at markup that is gone.
     *
     * The headings, not the words, are asserted absent — "Masuk" is still a pill in
     * the bar and "Beranda" is still the mark's accessible name, which is exactly
     * the point: the columns duplicated what a visitor had already been offered.
     */
    public function test_the_footer_renders_the_mark_and_the_legal_bar_only(): void
    {
        $footer = $this->footerMarkup();

        $this->assertStringNotContainsString(
            'data-footer-columns',
            $footer,
            'The footer still renders the removed column wrapper.',
        );

        foreach (['Situs', 'Akun', 'Tryout', 'Penilaian'] as $heading) {
            $this->assertStringNotContainsString(
                ">{$heading}<",
                $footer,
                "The footer still renders the removed [{$heading}] column heading.",
            );
        }

        // The same glyph BrandIdentityTest finds on the page, asserted here as
        // well so the footer's own guard does not lean on the header's copy.
        $this->assertStringContainsString(
            'M10.04 4.305c2.195-.667 4.615-.224 6.36 1.176',
            $footer,
            'The footer lost the brand mark that links back to the homepage.',
        );

        $this->assertStringContainsString('Copyright ©', $footer, 'The footer lost its legal bar.');

        // And the content file no longer offers columns to render.
        $this->assertArrayNotHasKey(
            'columns',
            app(ContentRepository::class)->chrome()['footer'],
            'resources/content/home/footer.php still defines link columns.',
        );
    }

    /**
     * Every link the footer renders answers a guest with the page itself.
     *
     * This is the rule the columns broke, and the reason they came out:
     * "Dashboard Peserta", "Ganti Password", "Riwayat Pengerjaan" and "Analisa
     * Skor" all redirect a signed-out visitor to /masuk, so the bottom of every
     * marketing page was advertising a login form. A destination that needs an
     * account does not belong in the footer.
     */
    public function test_every_footer_link_answers_a_guest_without_a_redirect(): void
    {
        $targets = [];

        foreach ($this->footerHrefs() as $href) {
            if ($href === '' || $href[0] === '#' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'http')) {
                continue;
            }

            $targets[strtok($href, '#')] = true;
        }

        $this->assertNotEmpty($targets, 'The footer rendered no internal links at all.');

        foreach (array_keys($targets) as $target) {
            $this->assertSame(
                200,
                $this->get($target)->getStatusCode(),
                "The footer links to [{$target}], which does not answer a guest directly.",
            );
        }
    }

    /** The rendered footer, sliced out so a word elsewhere on the page cannot satisfy an assertion. */
    private function footerMarkup(): string
    {
        $html = $this->get('/')->getContent();

        $start = strpos($html, 'data-section="footer"');

        $this->assertNotFalse($start, 'The homepage rendered no footer.');

        $end = strpos($html, '</footer>', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    /** @return list<string> */
    private function footerHrefs(): array
    {
        preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/', $this->footerMarkup(), $matches);

        return array_values(array_unique($matches[1]));
    }
}
