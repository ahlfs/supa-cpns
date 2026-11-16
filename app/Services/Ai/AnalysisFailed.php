<?php

declare(strict_types=1);

namespace App\Services\Ai;

use RuntimeException;
use Throwable;

/**
 * A generation that did not happen, carrying a message a participant may read.
 *
 * Every way this feature can fail ends here — unconfigured, unreachable, a
 * non-2xx status, an answer with no text in it, or a sitting that has not been
 * submitted — so the controller has exactly one thing to catch and the page has
 * exactly one voice: Indonesian, plain, and never a stack trace.
 */
final class AnalysisFailed extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Analisa AI belum dikonfigurasi di server ini.');
    }

    public static function unreachable(?Throwable $previous = null): self
    {
        return new self('Layanan analisa AI tidak bisa dihubungi. Coba lagi sebentar lagi.', 0, $previous);
    }

    public static function gateway(int $status): self
    {
        return new self('Layanan analisa AI menolak permintaan (kode '.$status.'). Coba lagi sebentar lagi.');
    }

    public static function emptyAnswer(): self
    {
        return new self('Layanan analisa AI tidak mengirim jawaban. Coba lagi sebentar lagi.');
    }

    public static function openSitting(): self
    {
        return new self('Ujian ini belum selesai, jadi belum ada hasil yang bisa dianalisa.');
    }

    /**
     * A follow-up question with nothing to follow up on. The conversation hangs
     * off the analysis, so asking about it before it exists has nowhere to go —
     * and answering with a fresh, unasked-for analysis would spend the expensive
     * generation on a question nobody put.
     */
    public static function missingAnalysis(): self
    {
        return new self('Belum ada analisa untuk percobaan ini. Buat analisanya dulu, lalu tanyakan bagian yang belum jelas.');
    }
}
