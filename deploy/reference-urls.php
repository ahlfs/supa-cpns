<?php

declare(strict_types=1);

/*
 * Print the paths a rendered page points at on its own site, one per line, so a
 * deploy can fetch every one of them and find the reference that resolves to
 * nothing. This is the check that catches a page which looks right while an
 * image, stylesheet or built asset behind it 404s.
 *
 *   php deploy/reference-urls.php --base=https://cpns.buatjalan.xyz < page.html
 *   curl -s https://cpns.buatjalan.xyz/ | php deploy/reference-urls.php --base=https://cpns.buatjalan.xyz
 *
 * URLs belonging to another host are skipped: answering for somebody else's
 * server is not this deploy's job. Both forms occur in the markup — `asset()`
 * and Vite write absolute URLs from APP_URL, content files write root-relative
 * paths — so `--base` decides what "this site" means. srcset is expanded,
 * because the page's images are chosen from it and a missing variant would
 * otherwise go unnoticed.
 */

$options = getopt('', ['base::']);
$base = rtrim((string) ($options['base'] ?? ''), '/');
$baseHost = $base !== '' ? (parse_url($base, PHP_URL_HOST) ?: '') : '';

$html = stream_get_contents(STDIN);

if ($html === false || $html === '') {
    fwrite(STDERR, "reference-urls: no HTML on stdin\n");
    exit(1);
}

$paths = [];

$collect = static function (string $value) use (&$paths, $baseHost): void {
    // srcset entries are "url descriptor, url descriptor": the URL is the first
    // field of each entry, and a descriptor may be missing entirely.
    foreach (explode(',', $value) as $candidate) {
        $fields = preg_split('/\s+/', trim($candidate));
        $url = html_entity_decode($fields[0] ?? '', ENT_QUOTES);

        if ($url === '' || str_starts_with($url, '//') || str_starts_with($url, 'data:')) {
            continue;
        }

        if (! str_starts_with($url, '/') && ! preg_match('~^https?://~i', $url)) {
            continue; // mailto:, tel:, #anchor …
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== null && $host !== false && $host !== $baseHost) {
            continue; // another site
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        $paths[$path.($query !== null && $query !== false ? '?'.$query : '')] = true;
    }
};

if (preg_match_all('/\b(?:src|href)\s*=\s*"([^"]*)"/i', $html, $matches)) {
    foreach ($matches[1] as $value) {
        $collect($value);
    }
}

if (preg_match_all('/\bsrcset\s*=\s*"([^"]*)"/i', $html, $matches)) {
    foreach ($matches[1] as $value) {
        $collect($value);
    }
}

$list = array_keys($paths);
sort($list);

foreach ($list as $path) {
    echo $path, "\n";
}

exit(0);
