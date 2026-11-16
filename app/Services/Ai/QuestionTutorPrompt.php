<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionChatMessage;

/**
 * What the tutor is asked, and nothing else: no HTTP, no database writes, no
 * clock. Given one item of one finished sitting it writes the facts down as
 * plain text, so the question the model is told and the question the participant
 * read on the page cannot drift apart.
 *
 * The item lives in the system message rather than in the first user turn. A
 * thread can grow past the history cap, and the cap drops the oldest turns — the
 * question, its options and its key must survive that, or a long conversation
 * ends up discussing an item nobody can see any more.
 *
 * The rules are the whole safety story of this feature, and they are deliberately
 * narrow. This is a page a participant reads to decide what to study, in a domain
 * where a confident wrong answer costs them a career, so the tutor is told to
 * answer only about the item in front of it, to refuse everything else in one
 * sentence, and to say it is unsure rather than fill a gap with a plausible
 * article number it was never given.
 */
final class QuestionTutorPrompt
{
    /**
     * The messages array for a chat-completions request: the fixed system message
     * carrying the rules and the item, then the conversation so far, oldest first,
     * ending with the question that was just asked.
     *
     * @param  iterable<QuestionChatMessage>  $history
     * @return list<array{role: string, content: string}>
     */
    public function messages(ExamAttempt $attempt, Question $question, iterable $history): array
    {
        $messages = [[
            'role' => 'system',
            'content' => $this->system($attempt, $question),
        ]];

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message->role->value,
                'content' => $message->content,
            ];
        }

        return $messages;
    }

    public function system(ExamAttempt $attempt, Question $question): string
    {
        return implode("\n", [
            'Kamu pendamping belajar peserta tryout SKD CPNS di Indonesia.',
            'Peserta sedang membuka pembahasan satu soal, dan kamu menjawab HANYA tentang soal itu.',
            '',
            'Aturan yang tidak boleh dilanggar:',
            '1. Jawab dalam bahasa Indonesia dan sapa peserta dengan "kamu".',
            '2. Pakai HANYA data soal di bawah ini: teks soal, kelima opsi beserta kunci atau bobotnya, pembahasan resminya, jawaban peserta, dan konsep yang langsung diuji soal itu.',
            '3. Kalau peserta meminta hal di luar soal ini — misalnya membuatkan skrip atau kode program, menulis artikel, menerjemahkan, mengerjakan soal lain, atau membahas topik lepas dari soal ini — tolak dengan satu kalimat pendek, contohnya: "Maaf, aku hanya bisa membahas soal ini." Lalu tawarkan menjelaskan bagian dari soal ini.',
            '4. Jangan mengarang pasal, ayat, nomor peraturan, nama lembaga, angka statistik, atau kutipan yang tidak ada di data soal. Kalau kamu tidak yakin, katakan bahwa kamu tidak yakin dan sarankan peserta membuka sumber resminya.',
            '5. Jangan menghitung ulang atau mengubah skor; nilainya sudah final.',
            '6. Jangan memakai emoji, tabel, atau format markdown (tanpa **, ##, tanda bintang, atau tanda pagar); tulis judul bagian sebagai kalimat biasa.',
            '7. Jawab singkat: paling banyak tiga paragraf pendek. Kalau peserta memang bertanya opsi satu per satu, paling banyak lima butir singkat.',
            '8. Jangan menyebut dirimu sebagai penentu kelulusan.',
            '',
            'DATA SOAL',
            $this->facts($attempt, $question),
            '',
            'Jawab hanya berdasarkan data soal di atas.',
        ]);
    }

    /**
     * The item as the participant sees it, plus the one thing only this feature
     * knows: which option they chose and what it earned them. Without that, the
     * most common question — "why is mine wrong?" — has nothing to answer.
     */
    public function facts(ExamAttempt $attempt, Question $question): string
    {
        $category = $question->category;
        $answer = $attempt->answers->firstWhere('question_id', $question->id);

        $lines = [
            'Subtes: '.$category->label().' ('.$category->name().')',
            'Submateri: '.$question->subcategoryLabel(),
            'Teks soal:',
            trim((string) $question->stem),
            '',
            'Opsi jawaban:',
        ];

        foreach ($question->options as $option) {
            $lines[] = $option->label.'. '.trim((string) $option->body).$this->optionNote($category, $option->is_correct, $option->score);
        }

        $lines[] = '';
        $lines[] = 'Pembahasan resmi: '.($question->explanation !== null && trim((string) $question->explanation) !== ''
            ? trim((string) $question->explanation)
            : '(belum ditulis)');

        $lines[] = '';
        $lines[] = 'JAWABAN PESERTA';

        // The verdict is the frozen one on the answer row, like everywhere else
        // on the result page: an admin who re-keys the item afterwards must not
        // change what this conversation says the participant scored.
        if ($answer === null || $answer->selected_option_id === null) {
            $lines[] = 'Peserta tidak menjawab soal ini (kosong), dan mendapat 0 poin.';
        } else {
            $chosen = $question->options->firstWhere('id', $answer->selected_option_id);
            $lines[] = 'Pilihan peserta: '.($chosen?->label ?? '?');

            if (! $category->requiresKey()) {
                $lines[] = 'Catatan: soal TKP tidak punya jawaban salah. Yang dinilai seberapa sesuai pilihan peserta dengan nilai ASN, '
                    .'dan soal ini memberi '.$answer->score_awarded.' dari '.$category->perItemMaximum().' poin.';
            } else {
                $lines[] = 'Hasil: '.($answer->is_correct === true ? 'benar' : 'salah')
                    .', poin '.$answer->score_awarded.' dari '.$category->scorePerCorrect().'.';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * A keyed subtest labels its one right answer; TKP has no right answer at
     * all, and its option weights are the only thing that separates a careful
     * choice from a careless one. `$isCorrect` is null on a TKP option — there is
     * no key to be right or wrong about — so it is nullable by design and never
     * read on that branch.
     */
    private function optionNote(QuestionCategory $category, ?bool $isCorrect, ?int $score): string
    {
        if (! $category->requiresKey()) {
            return $score === null ? '' : ' (bobot '.$score.' dari '.$category->perItemMaximum().')';
        }

        return $isCorrect ? ' (kunci jawaban)' : '';
    }
}
