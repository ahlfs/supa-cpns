<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">{{ isset($package) ? 'Ubah paket' : 'Paket baru' }}</h1>
    <p class="mt-2 text-body text-muted">
        Kolom ini hanya identitas paket. Daftar soalnya disusun di halaman berikutnya.
    </p>

    @if ($errors->any())
        <ul class="mt-5 space-y-1 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST"
        action="{{ isset($package) ? route('admin.packages.update', $package) : route('admin.packages.store') }}"
        class="mt-6 grid max-w-[720px] gap-4 rounded-card border border-line bg-white p-5 shadow-card">
        @csrf
        @if (isset($package))
            @method('PUT')
        @endif

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Judul</span>
            <input type="text" name="title" required value="{{ old('title', $package->title ?? '') }}"
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Slug</span>
            <span class="mb-1.5 block text-caption text-muted">Dipakai di URL. Kosongkan untuk dibuat dari judul.</span>
            <input type="text" name="slug" value="{{ old('slug', $package->slug ?? '') }}"
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Deskripsi</span>
            <textarea name="description" rows="2" class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">{{ old('description', $package->description ?? '') }}</textarea>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Durasi (menit)</span>
            <input type="number" name="duration_minutes"
                min="{{ config('cpns.duration_minutes_min') }}" max="{{ config('cpns.duration_minutes_max') }}" required
                value="{{ old('duration_minutes', $package->duration_minutes ?? config('cpns.duration_minutes')) }}"
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
        </label>

        <div class="flex gap-3">
            <button type="submit" class="rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark">Simpan</button>
            <a href="{{ route('admin.packages.index') }}" class="rounded-card border border-line px-5 py-3 font-heading text-small font-bold text-ink">Batal</a>
        </div>
    </form>
</x-layouts.admin>
