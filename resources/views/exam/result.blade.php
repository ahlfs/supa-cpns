{{--
    The results page wears the normal participant chrome, not the exam room's bare
    shell: the sitting is over, so a stray click costs nothing and the header and
    footer are an asset. It reads exactly what `ExamGrader` froze at submit.
--}}
<x-layouts.app :site="$site" :footer="$footer" :floating="$floating">
    <x-ui.app-page section="result">
        <div class="mx-auto max-w-page space-y-6">
            <header>
                <h1 class="font-heading text-h2 font-bold text-ink">Analisa skor</h1>
                <p class="mt-2 text-body text-muted">
                    {{ $attempt->package->title }} ·
                    {{ $attempt->status === \App\Enums\AttemptStatus::Expired ? 'waktu habis' : 'selesai' }} ·
                    {{ $attempt->submitted_at?->translatedFormat('d F Y, H:i') }}
                </p>
            </header>

            @include('exam.partials.result-scores', ['report' => $report, 'subtests' => $subtests])
            @include('exam.partials.result-analysis', ['attempt' => $attempt])
            @include('exam.partials.result-subtopics', ['subtopics' => $subtopics])
            @include('exam.partials.result-timing', ['timing' => $timing])
            @include('exam.partials.result-history', ['history' => $history, 'chart' => $chart, 'report' => $report])

            {{-- The card the review body swaps itself inside when a filter tab is
                 used. It is declared here, and deliberately not inside the partial,
                 so that it is never replaced: the module binds to this element and
                 its listeners outlive every swap. --}}
            <div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6" data-review>
                @include('exam.partials.result-review', ['review' => $review, 'filter' => $filter, 'attempt' => $attempt, 'aiReady' => $aiReady, 'chats' => $chats, 'open' => $open])
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <a href="{{ route('tryout.index') }}" class="inline-flex min-h-[44px] items-center rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">
                    Kembali ke daftar tryout
                </a>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">
                    Dashboard
                </a>
            </div>
        </div>
    </x-ui.app-page>
</x-layouts.app>
