<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the site's own identity.
 *
 * The marketing pages were rebuilt from a reference site, so the two things that
 * kept leaking back in are the reference's brand name and its outbound links.
 * These assertions make both impossible to reintroduce by accident: every page
 * carries our own mark, every link stays on this site, and every destination
 * actually resolves.
 */
final class BrandIdentityTest extends TestCase
{
    /** The pages that wear the marketing chrome. */
    private const PAGES = ['/', '/masuk', '/daftar'];

    /**
     * The cloud glyph that makes up the brand mark. Asserting the drawing rather
     * than the text means a missing logo cannot pass because the wordmark happens
     * to still be there.
     */
    private const CLOUD_PATH = 'M10.04 4.305c2.195-.667 4.615-.224 6.36 1.176';

    public function test_every_marketing_page_carries_the_brand_mark(): void
    {
        foreach (self::PAGES as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            $this->assertStringContainsString(self::CLOUD_PATH, $html, "The brand mark is missing from [{$page}].");
            $this->assertStringContainsString('SUPA', $html, "The wordmark is missing from [{$page}].");
        }
    }

    public function test_no_page_names_another_organisation(): void
    {
        // The reference's own brand, its domain and the entity behind it. None of
        // them may appear anywhere a visitor can read.
        $forbidden = ['asninstitute', 'ASNInstitute', 'ASN Institute', 'Digikreatif'];

        foreach (self::PAGES as $page) {
            $html = $this->get($page)->getContent();

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $needle,
                    $html,
                    "Page [{$page}] still names the reference brand: {$needle}.",
                );
            }
        }
    }

    public function test_every_link_the_marketing_chrome_renders_stays_on_this_site(): void
    {
        $html = $this->get('/')->getContent();
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        foreach ($this->hrefs($html) as $href) {
            if ($href === '' || $href[0] === '#' || str_starts_with($href, 'mailto:')) {
                continue;
            }

            // An absolute URL is fine as long as it points back at this site; a
            // different host is the reference's storefront creeping back in.
            if (str_starts_with($href, 'http')) {
                $this->assertSame(
                    $host,
                    parse_url($href, PHP_URL_HOST),
                    "The homepage links off-site: {$href}. Marketing chrome must not point at another domain.",
                );

                continue;
            }

            $this->assertStringStartsWith(
                '/',
                $href,
                "The homepage links off-site: {$href}. Marketing chrome must not point at another domain.",
            );
        }
    }

    public function test_every_internal_link_resolves(): void
    {
        $html = $this->get('/')->getContent();

        // One pass over the distinct targets, ignoring the fragment: an anchor to
        // `/#program` is the same route as `/`.
        $targets = [];

        foreach ($this->hrefs($html) as $href) {
            if ($href === '' || $href[0] === '#' || str_starts_with($href, 'mailto:')) {
                continue;
            }

            $targets[strtok($href, '#')] = true;
        }

        $this->assertNotEmpty($targets, 'The homepage rendered no internal links at all.');

        foreach (array_keys($targets) as $target) {
            $status = $this->get($target)->getStatusCode();

            $this->assertContains(
                $status,
                [200, 302],
                "The homepage links to [{$target}], which answered {$status}.",
            );
        }
    }

    /**
     * Every anchor target in a page, in document order.
     *
     * Only `<a href>` is considered: `<link>` and `@vite` emit absolute URLs built
     * with `asset()`, which are meant to be absolute and are not the navigation
     * this test is about.
     *
     * @return list<string>
     */
    private function hrefs(string $html): array
    {
        preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/', $html, $matches);

        return array_values(array_unique($matches[1]));
    }
}
