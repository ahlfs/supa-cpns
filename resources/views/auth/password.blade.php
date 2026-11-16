<x-layouts.app :site="$site" :footer="$footer" :floating="$floating">
    <x-ui.app-page section="auth" centered>
        <div class="rounded-card border border-line bg-white p-6 shadow-card lg:p-8">
            <h1 class="font-heading text-h2 font-bold text-ink">Ganti password</h1>

            @if ($forced)
                <div role="alert" class="mt-4 flex items-start gap-2.5 rounded-card border border-warning/30 bg-warning/5 px-4 py-3 text-small text-warning">
                    <x-icon name="clock" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Password sementara dari admin harus diganti dulu sebelum kamu bisa lanjut.</span>
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3">
                    <ul class="space-y-1 text-small text-danger">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Password saat ini</span>
                    <input
                        type="password"
                        name="current_password"
                        required
                        autofocus
                        autocomplete="current-password"
                        @error('current_password') aria-invalid="true" @enderror
                        @class([
                            'w-full rounded-card border bg-white px-3.5 py-2.5 text-body text-ink focus:outline-2',
                            'border-danger focus:outline-offset-0 focus:outline-danger' => $errors->has('current_password'),
                            'border-field focus:outline-offset-1 focus:outline-brand' => ! $errors->has('current_password'),
                        ])
                    >
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Password baru</span>
                    <input
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        aria-describedby="password-hint"
                        @error('password') aria-invalid="true" @enderror
                        @class([
                            'w-full rounded-card border bg-white px-3.5 py-2.5 text-body text-ink focus:outline-2',
                            'border-danger focus:outline-offset-0 focus:outline-danger' => $errors->has('password'),
                            'border-field focus:outline-offset-1 focus:outline-brand' => ! $errors->has('password'),
                        ])
                    >
                    <span id="password-hint" class="mt-1.5 block text-caption text-muted">
                        Minimal {{ config('cpns.password.min_length') }} karakter, campur huruf dan angka.
                    </span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Ulangi password baru</span>
                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        @error('password_confirmation') aria-invalid="true" @enderror
                        @class([
                            'w-full rounded-card border bg-white px-3.5 py-2.5 text-body text-ink focus:outline-2',
                            'border-danger focus:outline-offset-0 focus:outline-danger' => $errors->has('password_confirmation'),
                            'border-field focus:outline-offset-1 focus:outline-brand' => ! $errors->has('password_confirmation'),
                        ])
                    >
                </label>

                <button type="submit" class="w-full rounded-card bg-brand px-5 py-3 font-heading text-body font-bold text-white transition hover:bg-brand-dark focus:outline-2 focus:outline-offset-2 focus:outline-brand">
                    Simpan password baru
                </button>
            </form>
        </div>
    </x-ui.app-page>
</x-layouts.app>
