@props([
    'image',
    'class' => null,
    'sizes' => null,
    'loading' => null,
    'fetchpriority' => null,
])

{{--
    Renders one image from a content file. The content holds the image's path,
    variant widths, alt text and intrinsic size; this component turns that into
    the srcset/sizes markup so no partial repeats the boilerplate.
--}}
<img {{ $attributes->merge(\App\Support\Image::attributes($image, [
    'class' => $class,
    'sizes' => $sizes ?? ($image['sizes'] ?? null),
    'loading' => $loading,
    'fetchpriority' => $fetchpriority,
])) }}>
