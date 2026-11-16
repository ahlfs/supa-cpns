<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\QuestionCategory;
use App\Models\AttemptAnalysis;
use App\Models\AttemptAnalysisMessage;
use App\Models\ExamAttempt;
use App\Services\Exam\ScoreReport;

/**
 * The prompt the gateway is asked with, and nothing else: no HTTP, no database
 * writes, no clock. Given a graded sitting it writes the facts down as plain
 * structured text, so what the model is told and what the participant reads on
 * the page come from one source and cannot drift apart.
 *
 * The shape is deliberately stable and unadorned — labels, numbers and the
 * blueprint's own words — because the system message forbids inventing anything,
 * and a test can then assert on the exact lines the model receives.
 */
final class AnalysisPrompt
{
    /**
     * The messages array for a chat-completions request: one system message that
     * constrains the answer, one user message carrying the sitting's facts.
     *
     * @return list<array{role: string, content: string}>
     */
    public function messages(ExamAttempt $attempt): array
    {
        return [
            ['role' => 'system', 'content' => $this->system()],
            ['role' => 'user', 'content' => $this->facts($attempt)],
        ];
    }

    public function system(): string
    {
        return implode("\n", [
            'Kamu pendamping belajar peserta tryout SKD CPNS di Indonesia, dan namamu '.$this->botName().'.',
            'Ikuti aturan ini tanpa kecuali:',
            '1. Tulis jawaban dalam bahasa Indonesia dan sapa peserta dengan "kamu".',
            '2. Pakai HANYA angka dan nama yang tertulis di pesan pengguna.',
            '3. Jangan mengarang skor, ambang batas, statistik, persentil, nama subtes, atau nama submateri baru.',
            '4. Jangan mengubah atau menghitung ulang skor; nilainya sudah final.',
            '5. Ringkas dan konkret: paling banyak lima bagian pendek dengan judul singkat, tiap bagian dua sampai empat kalimat.',
            '6. Bagian terakhir wajib berupa rencana belajar singkat dan berurutan dari yang paling mendesak.',
            '7. Jangan memakai emoji, tabel, atau format markdown (tanpa **, ##, atau tanda bintang); tulis judul bagian sebagai kalimat biasa.',
            '8. Jangan menyebut dirimu sebagai penentu kelulusan.',
        ]);
    }

    /**
     * The rules for a question asked back about the analysis.
     *
     * Deliberately not the ones above. That prompt asks for five sections and a
     * study plan, which is right for a written analysis and wrong for a question:
     * somebody who asks why TIU is their weakest subtest wants an answer about
     * TIU, not a second study plan. The discipline is the same as the item
     * tutor's, though, and for the same reason — the facts on the page are all
     * the model was given, so it says it does not know rather than filling the
     * gap with a threshold or a percentile nobody wrote down.
     */
    public function followUpSystem(): string
    {
        return implode("\n", [
            'Kamu pendamping belajar peserta tryout SKD CPNS di Indonesia, dan namamu '.$this->botName().'.',
            'Peserta sudah membaca analisa hasil tryoutnya, dan sekarang bertanya balik tentang analisa itu.',
            '',
            'Aturan yang tidak boleh dilanggar:',
            '1. Jawab dalam bahasa Indonesia dan sapa peserta dengan "kamu".',
            '2. Pakai HANYA angka dan nama yang tertulis di data hasil tryout atau di analisa di atas.',
            '3. Jangan mengarang skor, ambang batas, statistik, persentil, nama subtes, atau nama submateri baru, dan jangan mengarang pasal atau peraturan.',
            '4. Jangan menghitung ulang atau mengubah skor; nilainya sudah final.',
            '5. Jawab langsung yang ditanyakan dan singkat: satu sampai tiga paragraf pendek. Jangan mengulang seluruh analisa.',
            '6. Kalau peserta meminta hal di luar hasil tryout ini dan rencana belajarnya — misalnya membuatkan skrip atau kode program, menulis artikel, menerjemahkan, atau membahas topik lepas — tolak dengan satu kalimat pendek, contohnya: "Maaf, aku hanya bisa membahas hasil tryout ini." Lalu tawarkan menjelaskan bagian lain dari hasilnya.',
            '7. Jangan memakai emoji, tabel, atau format markdown (tanpa **, ##, tanda bintang, atau tanda pagar).',
            '8. Kalau datanya tidak ada di atas, katakan bahwa kamu tidak tahu. Jangan menyebut dirimu sebagai penentu kelulusan.',
        ]);
    }

    /**
     * The messages array for a question asked back about the analysis.
     *
     * The sitting's facts travel again in the same shape the analysis was written
     * from, and the stored analysis is the assistant's opening turn: the question
     * is about that text, so a model asked without it is being asked about
     * something it cannot see. The turn just asked is the last entry of the
     * history, as it is for the item tutor — the caller stores it before asking,
     * because it is part of the prompt.
     *
     * @param  iterable<AttemptAnalysisMessage>  $history  oldest first, ending with the question just asked
     * @return list<array{role: string, content: string}>
     */
    public function followUp(ExamAttempt $attempt, AttemptAnalysis $analysis, iterable $history): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->followUpSystem()],
            ['role' => 'user', 'content' => $this->facts($attempt)],
            ['role' => 'assistant', 'content' => $analysis->content],
        ];

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message->role->value,
                'content' => $message->content,
            ];
        }

        return $messages;
    }

    /** The name the participant is talking to, from the one place that defines it. */
    private function botName(): string
    {
        $name = config('ai.bot_name');

        return is_string($name) && trim($name) !== '' ? trim($name) : 'SUPA Bot';
    }

    /**
     * The participant's own numbers, strongest and weakest alike, weakest
     * subtopics first because that list is what the study plan is built from.
     */
    public function facts(ExamAttempt $attempt): string
    {
        $report = new ScoreReport($attempt);
        $timing = $report->timing();

        $lines = [
            'HASIL TRYOUT SKD CPNS',
            'Paket: '.$attempt->package->title,
            'Status pengerjaan: '.$attempt->status->label(),
            'Skor total: '.$report->total().' dari '.$report->maximum()
                .' ('.($report->passed() ? 'lolos ambang batas semua subtes' : 'belum lolos ambang batas').')',
            '',
            'SKOR PER SUBTES',
        ];

        foreach ($report->subtests() as $subtest) {
            $lines[] = $subtest['label'].' ('.$subtest['name'].')';
            $lines[] = '  Skor '.$subtest['score'].' dari '.$subtest['max']
                .', ambang batas '.$subtest['threshold']
                .', '.($subtest['passed'] ? 'lolos' : 'kurang '.$subtest['shortfall'].' poin');

            // TKP has no key at all: its items are answered, never right or wrong,
            // and what separates good from careless answers is the option weight.
            $lines[] = $subtest['has_key']
                ? '  Benar '.$subtest['correct'].', salah '.$subtest['wrong'].', kosong '.$subtest['blank']
                : '  Terjawab '.$subtest['correct'].', kosong '.$subtest['blank'].', rata-rata bobot '.$subtest['average_weight'];
        }

        $lines[] = '';
        $lines[] = 'SUBTOPIK (terlemah lebih dulu, skor terhadap poin yang tersedia di submateri itu)';

        foreach ($report->subtopics() as $index => $row) {
            $lines[] = ($index + 1).'. '.$row['label'].' ('.$row['category'].')'
                .': skor '.$row['score'].' dari '.$row['available'].' ('.$row['ratio'].'%)'
                .($row['category'] === QuestionCategory::Tkp->label()
                    ? ', terjawab '.$row['correct'].', kosong '.$row['blank']
                    : ', benar '.$row['correct'].', salah '.$row['wrong'].', kosong '.$row['blank']);
        }

        $lines[] = '';
        $lines[] = 'WAKTU';
        $lines[] = 'Waktu terpakai: '.$timing['used_seconds'].' detik dari '.$timing['budget_seconds']
            .' detik ('.$timing['used_percent'].'%)';
        $lines[] = 'Rata-rata per soal: '.$timing['average_seconds'].' detik';
        $lines[] = 'Dijawab kurang dari '.$timing['guess_threshold'].' detik lalu salah: '.$timing['guesses'].' soal';
        $lines[] = 'Catatan: waktu per soal adalah perkiraan dari jarak antar perpindahan soal, bukan stopwatch, '
            .'dan satu soal paling lama dihitung '.config('cpns.timing_cap_seconds').' detik.';

        return implode("\n", $lines);
    }
}
