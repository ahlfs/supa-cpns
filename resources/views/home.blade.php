<x-layouts.app :site="$site" :footer="$footer ?? []" :floating="$floating ?? []">
    {{-- Section order is the page order. Each partial gets its own content file
         from resources/content/home and owns exactly one `data-section`.

         Sections are added here when they have something real to show. The
         "mitra", "as seen on", "pengajar" and "testimoni" blocks are deliberately
         absent: each one is a claim about third parties, and an empty shell (or a
         fabricated quote) is worse than not showing the block at all. --}}
    @include('sections.hero', ['hero' => $hero ?? []])
    @include('sections.stats', ['stats' => $stats ?? []])
    @include('sections.why', ['why' => $why ?? []])
    @include('sections.program-heading', ['programHeading' => $programHeading ?? []])
    @include('sections.programs', ['programs' => $programs ?? []])
    @include('sections.prefooter', ['prefooter' => $prefooter ?? []])
</x-layouts.app>
