<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\QuestionCategory;
use App\Models\ExamPackage;

/**
 * Whether a package is a real SKD exam. Publishing, the builder's shortfall bar
 * and the admin dashboard all ask this class, so the rule "30 TWK, 35 TIU, 45 TKP"
 * exists once and cannot drift between the three places that enforce it.
 */
final class PackageComposition
{
    /**
     * @return array<string, array{label: string, required: int, present: int, missing: int, complete: bool}>
     */
    public static function report(ExamPackage $package): array
    {
        // reorder(): questions() carries `order by exam_package_question.position`,
        // which is not in the group by and is rejected by MySQL's ONLY_FULL_GROUP_BY
        // and by Postgres. The result is keyed by category, so the order is unused.
        $present = $package->questions()
            ->reorder()
            ->selectRaw('questions.category as category, count(*) as total')
            ->groupBy('questions.category')
            ->pluck('total', 'category');

        $report = [];

        foreach (QuestionCategory::cases() as $category) {
            $required = $category->itemCount();
            $have = (int) ($present[$category->value] ?? 0);

            $report[$category->value] = [
                'label' => $category->label(),
                'required' => $required,
                'present' => $have,
                'missing' => max(0, $required - $have),
                'complete' => $have === $required,
            ];
        }

        return $report;
    }

    public static function isComplete(ExamPackage $package): bool
    {
        foreach (self::report($package) as $row) {
            if (! $row['complete']) {
                return false;
            }
        }

        return true;
    }

    /**
     * "TWK kurang 4, TKP lebih 2." Empty string when the package is complete.
     */
    public static function shortfallMessage(ExamPackage $package): string
    {
        $missing = [];

        foreach (self::report($package) as $row) {
            if ($row['complete']) {
                continue;
            }

            $missing[] = $row['present'] > $row['required']
                ? $row['label'].' lebih '.($row['present'] - $row['required'])
                : $row['label'].' kurang '.$row['missing'];
        }

        return $missing === [] ? '' : implode(', ', $missing).'.';
    }
}
