<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Image helper for the marketing assets.
 *
 * The images ship as pre-generated width variants (`…-w384.webp`) produced by the
 * original image pipeline; the content files list the widths rather than the
 * resulting srcset string, so a new size is one number instead of a rewrite.
 */
final class Image
{
    /**
     * Absolute URL for an asset path stored in resources/content.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset($path);
    }

    /**
     * Build a width-descriptor srcset from an image array.
     *
     * Either give `widths` + `path` + `extension`, or pass a ready-made `srcset`.
     *
     * @param  array<string, mixed>  $image
     */
    public static function srcset(array $image): ?string
    {
        if (! empty($image['srcset'])) {
            return self::rewriteCandidates((string) $image['srcset']);
        }

        $path = $image['path'] ?? null;
        $widths = $image['widths'] ?? null;

        if ($path === null || ! is_array($widths) || $widths === []) {
            return null;
        }

        $extension = '.'.ltrim((string) ($image['extension'] ?? 'webp'), '.');

        return implode(', ', array_map(
            static fn (int $width): string => self::url($path.'-w'.$width.$extension).' '.$width.'w',
            $widths,
        ));
    }

    /**
     * The full attribute bag for an <img>, with nulls and empty values dropped.
     *
     * @param  array<string, mixed>  $image
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    public static function attributes(array $image, array $overrides = []): array
    {
        $attributes = [
            'src' => self::url($image['src'] ?? $image['path'] ?? null),
            'srcset' => self::srcset($image),
            'sizes' => $image['sizes'] ?? null,
            'alt' => $image['alt'] ?? '',
            'width' => $image['width'] ?? null,
            'height' => $image['height'] ?? null,
            'loading' => $image['loading'] ?? 'lazy',
            'decoding' => $image['decoding'] ?? 'async',
            'fetchpriority' => $image['fetchpriority'] ?? null,
            'aria-hidden' => $image['aria_hidden'] ?? null,
            'class' => $image['class'] ?? null,
        ];

        $attributes = array_replace($attributes, array_filter($overrides, static fn ($value): bool => $value !== null));

        // An attribute that ended up null or '' must not be printed at all: an
        // empty sizes="" or fetchpriority="" is worse than omitting it.
        return array_map(static fn ($value): string => (string) $value, array_filter(
            $attributes,
            static fn ($value): bool => $value !== null && $value !== '',
        ));
    }

    /**
     * Point every candidate of an existing srcset at its local copy.
     */
    private static function rewriteCandidates(string $srcset): string
    {
        return implode(', ', array_map(static function (string $candidate): string {
            $parts = preg_split('/\s+/', trim($candidate), 2) ?: [];
            $url = self::url($parts[0]) ?? '';

            return isset($parts[1]) ? $url.' '.$parts[1] : $url;
        }, explode(',', $srcset)));
    }
}
