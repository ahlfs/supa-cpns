<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Ubah soal</h1>
    <p class="mt-2 text-body text-muted">
        Soal boleh diubah kapan saja. Teks dan pembahasan dibaca langsung dari bank, jadi perbaikan
        langsung terlihat peserta lama; yang tidak berubah adalah skor percobaan yang sudah selesai.
    </p>

    @include('admin.questions.form', [
        'question' => $question,
        'category' => $category,
        'action' => route('admin.questions.update', $question),
        'method' => 'PUT',
    ])
</x-layouts.admin>
