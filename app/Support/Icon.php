<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Renders the vendored SVG icons in resources/icons.
 *
 * The files are real artwork committed to the repository — no icon-font, no CDN
 * and no runtime download — so a page never depends on the network for its
 * glyphs. The component owns size and colour; the artwork keeps its own paint
 * attributes (fill, stroke) so different icon families render correctly.
 */
final class Icon
{
    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * @param  array<string, string|int|bool>  $attributes
     */
    public static function render(string $name, array $attributes = []): string
    {
        [$rootAttributes, $inner] = self::parts($name);

        $attributes = array_filter(
            $attributes + ['aria-hidden' => 'true', 'focusable' => 'false'],
            static fn ($value): bool => $value !== null && $value !== false && $value !== '',
        );

        $merged = $rootAttributes + array_map(static fn ($value): string => (string) $value, $attributes);

        $rendered = '';
        foreach ($merged as $attribute => $value) {
            $rendered .= ' '.$attribute.'="'.e($value).'"';
        }

        return '<svg'.$rendered.'>'.$inner.'</svg>';
    }

    public static function exists(string $name): bool
    {
        return is_file(self::path($name));
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private static function parts(string $name): array
    {
        $markup = self::$cache[$name] ??= self::read($name);

        if (preg_match('/^<svg\s+([^>]*?)>(.*)<\/svg>\s*$/s', $markup, $matches) !== 1) {
            throw new RuntimeException("Icon [{$name}] is not a single root <svg> element.");
        }

        preg_match_all('/([a-zA-Z:-]+)="([^"]*)"/', $matches[1], $attributeMatches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($attributeMatches as [, $attribute, $value]) {
            // Presentation attributes the artwork needs, minus anything the caller
            // controls (size, class, style are set by the component or the layout).
            if (! in_array($attribute, ['width', 'height', 'class', 'style'], true)) {
                $attributes[$attribute] = $value;
            }
        }

        return [$attributes, $matches[2]];
    }

    private static function read(string $name): string
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            throw new RuntimeException("Icon name [{$name}] is not a valid slug.");
        }

        $path = self::path($name);

        if (! is_file($path)) {
            throw new RuntimeException("Icon [{$name}] is missing: expected {$path}.");
        }

        return (string) file_get_contents($path);
    }

    private static function path(string $name): string
    {
        return resource_path('icons/'.$name.'.svg');
    }
}
