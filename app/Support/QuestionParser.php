<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\QuestionCategory;
use App\Models\QuestionOption;

/**
 * Turns a pasted block or a CSV body into rows the question form's own validation
 * would also accept, plus a per-line list of what is wrong with the rest.
 *
 * Two formats, one header row required:
 *
 *   TWK/TIU  category, subcategory, stem, option_a…option_e, key, explanation
 *   TKP      category, subcategory, stem, option_a…option_e, score_a…score_e
 *
 * Delimiter is a tab when the header contains one (what you get by pasting out of
 * a spreadsheet) and a comma otherwise.
 *
 * Every row is checked before a single one is written, and the preview names the
 * line and the reason for the ones that failed: a half-imported bank hides broken
 * items until a participant meets one, which is much more expensive than a refused
 * import.
 */
final class QuestionParser
{
    /** The columns both shapes share. */
    private const SHARED = ['category', 'subcategory', 'stem', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e'];

    /** The columns a keyed item adds — a single key and an optional explanation. */
    private const KEYED = ['key', 'explanation'];

    /** The columns a weighted item adds: one weight per option. */
    private const WEIGHTED = ['score_a', 'score_b', 'score_c', 'score_d', 'score_e'];

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function parse(string $payload): array
    {
        // An Excel CSV export opens with a byte-order mark, which would otherwise
        // make the first header cell unrecognisable.
        $payload = ltrim(trim($payload), "\xEF\xBB\xBF");

        if ($payload === '') {
            return ['rows' => [], 'errors' => [1 => 'Tidak ada isi yang bisa dibaca.']];
        }

        $delimiter = str_contains(strtok($payload, "\n"), "\t") ? "\t" : ',';
        $lines = preg_split('/\r\n|\r|\n/', $payload) ?: [];
        $header = $this->header(array_shift($lines), $delimiter);

        if ($header === []) {
            return ['rows' => [], 'errors' => [1 => 'Baris pertama harus berisi nama kolom.']];
        }

        $rows = [];
        $errors = [];

        foreach ($lines as $offset => $line) {
            $lineNumber = $offset + 2;

            if (trim($line) === '') {
                continue;
            }

            $values = array_map('trim', str_getcsv($line, $delimiter));
            $record = $this->record($header, $values);

            if ($record === null) {
                $errors[$lineNumber] = $this->cellCountMessage($header, $values);

                continue;
            }

            [$row, $error] = $this->row($record, $values);

            if ($error !== null) {
                $errors[$lineNumber] = $error;

                continue;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @return list<string>
     */
    private function header(string|false $line, string $delimiter): array
    {
        if ($line === false) {
            return [];
        }

        return array_map(
            fn (string $column): string => strtolower(trim($column)),
            str_getcsv($line, $delimiter),
        );
    }

    /**
     * One line, keyed by the header's column names — or null when the line carries a
     * cell count no shape accounts for.
     *
     * The width is settled before anything is padded or sliced, because slicing is
     * what turns an over-long row into a shifting one: the leading cells stay the
     * value window while the shape's own cells are read from the tail, so a TKP row
     * whose stem carried an unquoted comma imported with its option bodies one cell
     * to the left and its stem cut — silently. Only a line of an accepted width gets
     * as far as the pad and the slice, and for the narrower shape in a mixed block
     * that slice is what lines the row up with the header's names.
     *
     * @param  list<string>  $header
     * @param  list<string>  $values
     * @return array<string, string>|null
     */
    private function record(array $header, array $values): ?array
    {
        if (! in_array(count($values), $this->widths($header, $values), true)) {
            return null;
        }

        $values = array_pad(array_slice($values, 0, count($header)), count($header), '');

        return array_combine($header, $values) ?: null;
    }

    /**
     * The cell counts one line may carry: the header's own width, and — when a mixed
     * block puts the other shape's rows under this header — the width of the shape the
     * line itself declares, which is the 8 shared cells plus its own 2 or 5. A TKP row
     * under the TWK header is exactly 8 + 5 cells, which is what keeps the documented
     * mixed block working.
     *
     * Both counts are exact. A line one cell off either is refused with its counts
     * named, never padded or sliced into a shifted item.
     *
     * @param  list<string>  $header
     * @param  list<string>  $values
     * @return list<int>
     */
    private function widths(array $header, array $values): array
    {
        $widths = [count($header)];
        $category = QuestionCategory::tryFrom(strtolower($this->cell($header, $values, 'category')));

        if ($category !== null) {
            $own = $category->requiresKey() ? self::KEYED : self::WEIGHTED;
            $widths[] = count(self::SHARED) + count($own);
        }

        sort($widths);

        return array_values(array_unique($widths));
    }

    /**
     * One cell of a line, addressed by the header's name for its column. Blank when
     * the header does not name that column or the line is too short to reach it.
     *
     * @param  list<string>  $header
     * @param  list<string>  $values
     */
    private function cell(array $header, array $values, string $column): string
    {
        $index = array_search($column, $header, true);

        return $index === false ? '' : ($values[$index] ?? '');
    }

    /**
     * The refusal for a line whose width is not one the block accounts for. It names
     * both counts, because a bare "mismatch" sends people back to guess which cell
     * they lost or gained.
     *
     * @param  list<string>  $header
     * @param  list<string>  $values
     */
    private function cellCountMessage(array $header, array $values): string
    {
        $expected = implode(' atau ', $this->widths($header, $values));

        return 'Jumlah kolom tidak cocok: baris ini punya '.count($values)." kolom, bukan {$expected}.";
    }

    /**
     * @param  array<string, string>  $record
     * @param  list<string>  $values
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function row(array $record, array $values): array
    {
        $category = QuestionCategory::tryFrom(strtolower($record['category'] ?? ''));

        if ($category === null) {
            return [null, 'Kolom category harus twk, tiu, atau tkp.'];
        }

        if (! array_key_exists($record['subcategory'] ?? '', $category->subcategories())) {
            return [null, 'Submateri ['.($record['subcategory'] ?? '').'] tidak ada di kisi-kisi '.$category->label().'.'];
        }

        $stem = $record['stem'] ?? '';

        if (mb_strlen($stem) < 10) {
            return [null, 'Teks soal terlalu pendek.'];
        }

        $options = [];

        foreach (self::letters() as $letter) {
            $body = $record['option_'.$letter] ?? '';

            if ($body === '') {
                return [null, 'Opsi '.strtoupper($letter).' masih kosong.'];
            }

            $options[strtoupper($letter)] = ['body' => $body];
        }

        $row = [
            'category' => $category->value,
            'subcategory' => $record['subcategory'],
            'stem' => $stem,
            'explanation' => null,
            'is_active' => true,
        ];

        if ($category->requiresKey()) {
            $shape = $this->shape($record, $values, self::KEYED);
            $key = strtoupper($shape['key'] ?? '');

            if (! in_array($key, QuestionOption::LETTERS, true)) {
                return [null, 'Kolom key harus salah satu dari '.self::letterRange().'.'];
            }

            $explanation = $shape['explanation'] ?? '';

            return [[
                ...$row,
                'explanation' => $explanation === '' ? null : $explanation,
                'options' => $options,
                'key' => $key,
            ], null];
        }

        $shape = $this->shape($record, $values, self::WEIGHTED);

        foreach (QuestionOption::LETTERS as $letter) {
            $options[$letter]['score'] = (int) ($shape['score_'.strtolower($letter)] ?? 0);
        }

        // The ladder comes from the blueprint's per-item maximum, the same number
        // the form's weight rule and its select are built from — never a literal 5.
        $ladder = range(1, $category->perItemMaximum());

        if (! $this->sameLadder($options, $ladder)) {
            return [null, $this->ladderMessage($ladder)];
        }

        return [[...$row, 'options' => $options], null];
    }

    /**
     * The shape-specific cells of one line, keyed by column name.
     *
     * The header normally names them. It cannot when one block mixes keyed and
     * weighted rows under a single header — which is what pasting both out of the
     * same spreadsheet looks like — so a row whose own shape is not named anywhere
     * reads its shape columns from the tail of the line, where both formats put
     * them. That count is exact: record() has already refused every line whose width
     * is not one of the two a shape accounts for, so a tail read can never take its
     * cells from a line carrying an extra one and leave the head cells shifted.
     *
     * @param  array<string, string>  $record
     * @param  list<string>  $values
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function shape(array $record, array $values, array $columns): array
    {
        $declared = array_intersect($columns, array_keys($record));

        if ($declared === $columns) {
            return array_intersect_key($record, array_flip($columns));
        }

        if ($declared === [] && count($values) === count(self::SHARED) + count($columns)) {
            return (array) array_combine($columns, array_slice($values, -count($columns)));
        }

        return array_fill_keys($columns, '');
    }

    /**
     * @param  array<string, array{body: string, score: int}>  $options
     * @param  list<int>  $ladder
     */
    private function sameLadder(array $options, array $ladder): bool
    {
        $scores = array_column($options, 'score');
        sort($scores);

        return $scores === $ladder;
    }

    /**
     * The same refusal the question form makes, built from the same ladder.
     *
     * @param  list<int>  $ladder
     */
    private function ladderMessage(array $ladder): string
    {
        if (count($ladder) < 2) {
            return 'Bobot TKP tidak sesuai kisi-kisi.';
        }

        $readable = implode(', ', array_slice($ladder, 0, -1)).', dan '.$ladder[count($ladder) - 1];

        return "Bobot TKP harus tepat {$readable} — tidak boleh ada bobot kembar.";
    }

    /**
     * The option letters, lowercased for a column name. The A–E set itself lives
     * only at QuestionOption::LETTERS.
     *
     * @return list<string>
     */
    private static function letters(): array
    {
        return array_map('strtolower', QuestionOption::LETTERS);
    }

    /**
     * "A sampai E" for the key refusal, read off the same letter set the check reads,
     * so a widened or relettered option list cannot leave the message behind.
     */
    private static function letterRange(): string
    {
        $letters = QuestionOption::LETTERS;

        return $letters[0].' sampai '.$letters[count($letters) - 1];
    }
}
