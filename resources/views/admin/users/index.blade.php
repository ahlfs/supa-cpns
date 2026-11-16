<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Pengguna</h1>
    <p class="mt-2 text-body text-muted">{{ $users->total() }} akun.</p>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    @if (session('temporary_password'))
        @php($reset = session('temporary_password'))
        <div class="mt-5 rounded-card border border-warning/40 bg-warning/5 px-4 py-4 text-small text-warning">
            <p class="font-semibold">Password sementara untuk {{ $reset['name'] }} ({{ $reset['email'] }}):</p>
            <p class="mt-2 font-mono text-body font-bold">{{ $reset['password'] }}</p>
            <p class="mt-2">
                Ini hanya tampil sekali dan tidak dikirim ke mana-mana. Berikan ke pemiliknya langsung; akun ini
                wajib mengganti password saat pertama masuk.
            </p>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 flex gap-3">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama, email, atau nomor peserta"
            class="w-full max-w-[420px] rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
        <button type="submit" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink">
            Cari
        </button>
    </form>

    <div class="mt-5 space-y-3">
        @forelse ($users as $user)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <strong class="font-heading text-body font-bold text-ink">{{ $user->name }}</strong>
                        <p class="text-small text-muted">
                            {{ $user->email }} · {{ $user->participant_number ?? 'tanpa nomor' }} ·
                            {{ $user->attempts_count }} percobaan
                        </p>
                        <p class="mt-1 text-caption">
                            <span class="{{ $user->isAdmin() ? 'text-brand' : 'text-muted' }}">{{ $user->role->label() }}</span>
                            @if ($user->must_change_password)
                                <span class="text-warning">· menunggu ganti password</span>
                            @endif
                        </p>
                    </div>

                    @if ($user->isAdmin())
                        {{-- The server refuses this for every administrator, so the button is
                             not offered rather than offered and rejected. --}}
                        <p class="text-caption text-muted">
                            Password administrator tidak bisa direset dari layar ini.
                        </p>
                    @else
                        {{-- The name is participant-controlled and this is an inline handler: interpolated
                             directly into the string literal, a quote in a name survives Blade's `&#039;`
                             because an HTML parser decodes the entity before the JavaScript parser runs —
                             the quote reappears, closes the literal, and the rest of the name executes in
                             the administrator's session. `@js` emits the name as its own JS string literal
                             with the dangerous characters unicode-escaped, so it is inert both inside this
                             attribute and in JavaScript. --}}
                        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                            onsubmit="return confirm('Buat password sementara untuk ' + @js($user->name) + '?')">
                            @csrf
                            <button type="submit" class="rounded-card border border-line px-4 py-2.5 font-heading text-small font-bold text-ink transition hover:border-warning hover:text-warning">
                                Reset password
                            </button>
                        </form>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_160px_auto]">
                    @csrf
                    @method('PUT')

                    <input type="text" name="name" value="{{ $user->name }}" required
                        aria-label="Nama {{ $user->name }}"
                        class="rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                    <input type="email" name="email" value="{{ $user->email }}" required
                        aria-label="Email {{ $user->name }}"
                        class="rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                    <select name="role" aria-label="Peran {{ $user->name }}"
                        class="rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                        @foreach (\App\Enums\UserRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                        Simpan
                    </button>
                </form>
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">
                Tidak ada akun yang cocok.
            </p>
        @endforelse
    </div>

    <div class="mt-5">{{ $users->links() }}</div>
</x-layouts.admin>
