<x-layouts.app :site="$site" :footer="$footer" :floating="$floating">
    <x-ui.app-page section="auth" centered>
        <div class="rounded-card border border-line bg-white p-6 shadow-card lg:p-8">
            <h1 class="font-heading text-h2 font-bold text-ink">Daftar akun</h1>
            <p class="mt-2 text-body text-muted">Gratis. Semua paket tryout terbuka begitu akunnya jadi.</p>

            @if ($errors->any())
                <div role="alert" class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3">
                    <ul class="space-y-1 text-small text-danger">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Nama lengkap</span>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        autocomplete="name"
                        @error('name') aria-invalid="true" @enderror
                        @class([
                            'w-full rounded-card border bg-white px-3.5 py-2.5 text-body text-ink focus:outline-2',
                            'border-danger focus:outline-offset-0 focus:outline-danger' => $errors->has('name'),
                            'border-field focus:outline-offset-1 focus:outline-brand' => ! $errors->has('name'),
                        ])
                    >
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Email</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        @error('email') aria-invalid="true" @enderror
                        @class([
                            'w-full rounded-card border bg-white px-3.5 py-2.5 text-body text-ink focus:outline-2',
                            'border-danger focus:outline-offset-0 focus:outline-danger' => $errors->has('email'),
                            'border-field focus:outline-offset-1 focus:outline-brand' => ! $errors->has('email'),
                        ])
                    >
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Password</span>
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
                    <span class="mb-1.5 block text-small font-semibold text-ink">Ulangi password</span>
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
                    Daftar
                </button>
            </form>

            <p class="mt-6 text-small text-muted">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-brand hover:text-brand-dark focus:outline-2 focus:outline-offset-2 focus:outline-brand">Masuk</a>
            </p>
        </div>
    </x-ui.app-page>
</x-layouts.app>
