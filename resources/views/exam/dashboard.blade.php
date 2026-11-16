<x-layouts.app :site="$site" :footer="$footer" :floating="$floating">
    <x-ui.app-page section="dashboard">
        <div class="mx-auto max-w-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-heading text-h2 font-bold text-ink">Halo, {{ auth()->user()->name }}</h1>
                    <p class="mt-2 text-body text-muted">
                        Nomor peserta <span class="font-semibold text-ink">{{ auth()->user()->participant_number }}</span>
                    </p>
                </div>

                {{-- A POST form, not a link: signing out changes state and stays CSRF-protected. --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink transition hover:border-brand hover:text-brand">
                        Keluar
                    </button>
                </form>
            </div>

            @if (auth()->user()->isAdmin())
                {{--
                    The panel is deliberately absent from the participant
                    navigation: running the bank of questions is a different job
                    from sitting a tryout. This card is where it announces
                    itself, and only to an administrator.
                --}}
                <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-card border border-brand/25 bg-brand/5 p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-card bg-brand text-white">
                            <x-icon name="grid" class="h-5 w-5" />
                        </span>
                        <div>
                            <strong class="block font-heading text-body font-bold text-ink">Panel admin</strong>
                            <span class="text-small text-muted">Kelola bank soal, paket tryout, impor massal, dan akun peserta.</span>
                        </div>
                    </div>

                    <a href="{{ route('admin.dashboard') }}"
                        class="rounded-card bg-brand px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                        Buka panel
                    </a>
                </div>
            @endif

            @if (session('status'))
                <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">
                    {{ session('status') }}
                </p>
            @endif

            @if ($open->isNotEmpty())
                <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Sedang dikerjakan</h2>
                <div class="mt-4 grid gap-4">
                    @foreach ($open as $attempt)
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-card border border-warning/30 bg-white p-5 shadow-card">
                            <div>
                                <strong class="block font-heading text-body font-bold text-ink">{{ $attempt->package->title }}</strong>
                                <span class="text-small text-muted">Sisa waktu {{ intdiv($attempt->remainingSeconds(), 60) }} menit</span>
                            </div>
                            <a href="{{ route('tryout.room', $attempt) }}"
                                class="rounded-card bg-brand px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                                Lanjutkan
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Paket tryout</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @forelse ($packages as $package)
                    @include('exam.partials.package-card', [
                        'package' => $package,
                        'openAttempt' => $openAttempts->get($package->id),
                    ])
                @empty
                    <p class="text-body text-muted">Belum ada paket yang dibuka. Cek lagi nanti ya.</p>
                @endforelse
            </div>

            @if ($finished->isNotEmpty())
                <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Riwayat</h2>
                <div class="mt-4 overflow-hidden rounded-card border border-line bg-white shadow-card">
                    <table class="w-full text-left text-small">
                        <thead class="bg-surface text-caption uppercase text-muted">
                            <tr>
                                <th class="px-4 py-3">Paket</th>
                                <th class="px-4 py-3">Skor</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($finished as $attempt)
                                <tr class="border-t border-line">
                                    <td class="px-4 py-3 text-ink">{{ $attempt->package->title }}</td>
                                    <td class="px-4 py-3 font-semibold text-ink">{{ $attempt->total_score }} / {{ config('cpns.total_max') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="{{ $attempt->hasPassed() ? 'text-success' : 'text-danger' }}">
                                            {{ $attempt->hasPassed() ? 'Lolos ambang' : 'Belum lolos' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('hasil.show', $attempt) }}" class="font-semibold text-brand hover:text-brand-dark">Analisa</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-ui.app-page>
</x-layouts.app>
