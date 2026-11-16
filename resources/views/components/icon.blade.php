@props([
    'name',
    'size' => null,
])

{{--
    Inlines a vendored SVG from resources/icons. Size with a class
    (`class="h-4 w-4"`) or with the `size` prop for a square icon.
--}}
{!! \App\Support\Icon::render($name, $attributes->getAttributes() + array_filter(['width' => $size, 'height' => $size])) !!}
