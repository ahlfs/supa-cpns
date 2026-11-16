<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three SKD subtests. TWK and TIU are graded against a key; TKP is graded by
 * the weight on the chosen option, because in TKP every option is defensible and
 * only its fit with the ASN values separates them.
 */
enum QuestionCategory: string
{
    case Twk = 'twk';
    case Tiu = 'tiu';
    case Tkp = 'tkp';

    public function label(): string
    {
        return (string) $this->config('label');
    }

    public function name(): string
    {
        return (string) $this->config('name');
    }

    /** True when the item needs a single correct option. TKP never does. */
    public function requiresKey(): bool
    {
        return $this !== self::Tkp;
    }

    public function itemCount(): int
    {
        return (int) $this->config('items');
    }

    public function maxScore(): int
    {
        return (int) $this->config('max');
    }

    public function scorePerCorrect(): int
    {
        return (int) $this->config('correct');
    }

    public function threshold(): int
    {
        return (int) $this->config('threshold');
    }

    /**
     * The most one item in this subtest can be worth: the subtest maximum spread
     * over its blueprint item count.
     *
     * For the keyed subtests this is just the points a correct answer earns. For
     * TKP every option is defensible and the weight on the chosen option *is* the
     * points awarded, so the ceiling on an option weight and the ceiling on the
     * points one item can award are the same number by construction. It is that
     * identity — not the literal 5 — that lets one method serve TWK, TIU and the
     * keyless TKP without a second branch.
     */
    public function perItemMaximum(): int
    {
        $items = $this->itemCount();

        return $items === 0 ? 0 : intdiv($this->maxScore(), $items);
    }

    /** @return array<string, string> subtopic key => human label */
    public function subcategories(): array
    {
        return (array) config('cpns.subcategories.'.$this->value, []);
    }

    public function subcategoryLabel(string $key): string
    {
        return $this->subcategories()[$key] ?? $key;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return config('cpns.subtests.'.$this->value.'.'.$key, $default);
    }
}
