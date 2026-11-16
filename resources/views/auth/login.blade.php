<x-layouts.app :site="$site" :footer="$footer" :floating="$floating">
    <x-ui.app-page section="auth" centered>
        <div class="rounded-card border border-line bg-white p-6 shadow-card lg:p-8">
            <h1 class="font-heading text-h2 font-bold text-ink">Masuk</h1>
            <p class="mt-2 text-body text-muted">Lanjutkan latihan SKD kamu dan lihat analisa skornya.</p>

            {{-- Every message, not just the first: a failed sign-in can report
                 both fields at once and showing one at a time makes the visitor
                 resubmit to discover the second. --}}
            @if ($errors->any())
                <div role="alert" class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3">
                    <ul class="space-y-1 text-small text-danger">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Email</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
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
                        autocomplete="current-password"
                        @error('password') aria-invalid="true" @enderror
                        @class([
                            'w-full rounded-card border bg-white px-3.5 py-2.5 text-body text-ink focus:outline-2',
                            'border-danger focus:outline-offset-0 focus:outline-danger' => $errors->has('password'),
                            'border-field focus:outline-offset-1 focus:outline-brand' => ! $errors->has('password'),
                        ])
                    >
                </label>

                {{-- The box is drawn by the span so its border carries the same
                     3:1 contrast as the inputs; the native widget would ignore
                     the token and render a grey square. The icon is a sibling of
                     the input, which is what makes `peer-checked` reach it. --}}
                <label class="flex cursor-pointer items-center gap-2.5 py-1.5 text-small text-muted">
                    <span class="relative grid h-[18px] w-[18px] shrink-0">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="peer h-full w-full cursor-pointer appearance-none rounded-[5px] border border-field bg-white transition-colors checked:border-brand checked:bg-brand focus:outline-2 focus:outline-offset-1 focus:outline-brand"
                        >
                        <x-icon name="check" class="pointer-events-none absolute inset-0 m-auto h-3 w-3 text-white opacity-0 transition-opacity peer-checked:opacity-100" />
                    </span>
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button type="submit" class="w-full rounded-card bg-brand px-5 py-3 font-heading text-body font-bold text-white transition hover:bg-brand-dark focus:outline-2 focus:outline-offset-2 focus:outline-brand">
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-small text-muted">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-semibold text-brand hover:text-brand-dark focus:outline-2 focus:outline-offset-2 focus:outline-brand">Daftar gratis</a>
            </p>
        </div>
    </x-ui.app-page>
</x-layouts.app>
