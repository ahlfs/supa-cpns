<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads the marketing copy that ships with the app.
 *
 * Each page is a directory of plain PHP files under resources/content, one file
 * per section, and each file returns a nested array of copy, links and image
 * paths. Keeping content out of the templates is what makes the site editable
 * without touching Blade (and without a database for a page that never changes
 * per visitor).
 */
final class ContentRepository
{
    public function __construct(private readonly string $root) {}

    /**
     * The site-wide values every page needs: brand, contact details, CTAs.
     *
     * @return array<string, mixed>
     */
    public function site(): array
    {
        return $this->require('site.php');
    }

    /**
     * The site-wide chrome the layout renders around every page: the footer
     * blocks and the floating WhatsApp/back-to-top bubble. It lives under
     * resources/content/home because that is where the marketing rebuild put it,
     * but it is not homepage-specific — the layout needs it on every page, and an
     * empty array is a fatal, not a fallback.
     *
     * @return array{footer: array<string, mixed>, floating: array<string, mixed>}
     */
    public function chrome(): array
    {
        return [
            'footer' => $this->require('home/footer.php'),
            'floating' => $this->require('home/floating.php'),
        ];
    }

    /**
     * Every section of a page, keyed by section name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function page(string $page): array
    {
        $directory = $this->root.'/'.$page;

        if (! is_dir($directory)) {
            throw new RuntimeException("No content directory for page [{$page}] at {$directory}.");
        }

        $sections = [];

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            // Content files are named after the design (program-heading.php) while
            // the view needs a usable variable name (programHeading).
            $sections[Str::camel(basename($file, '.php'))] = $this->require($page.'/'.basename($file));
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    private function require(string $relativePath): array
    {
        $path = $this->root.'/'.$relativePath;

        if (! is_file($path)) {
            throw new RuntimeException("Missing content file: {$path}");
        }

        $content = require $path;

        if (! is_array($content)) {
            throw new RuntimeException("Content file {$path} must return an array.");
        }

        return $this->resolveRoutes($content);
    }

    /**
     * Turn every `route` key into the `url` the views read.
     *
     * Content files name internal destinations by route (or by plain URL for an
     * in-page anchor), so a renamed route can never leave a dead link in the
     * header or the footer: the path is generated at read time, and a route that
     * no longer exists raises the router's own exception instead of shipping a
     * 404. An explicit `url` always wins, so a link may carry both.
     *
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    private function resolveRoutes(array $content): array
    {
        if (isset($content['route']) && is_string($content['route']) && ! isset($content['url'])) {
            // Relative, so the navigation a visitor sees does not carry the
            // environment's hostname and the markup stays portable between the
            // local server and production.
            $content['url'] = route($content['route'], absolute: false);
        }

        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $content[$key] = $this->resolveRoutes($value);
            }
        }

        return $content;
    }
}
