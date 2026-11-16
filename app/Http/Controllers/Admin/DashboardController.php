<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionCategory;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Support\ContentRepository;
use App\Support\PackageComposition;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __invoke(ContentRepository $content): View
    {
        $packages = ExamPackage::query()->withCount('questions')->orderBy('title')->get();

        // The list that actually gets used: packages that are not yet a real exam,
        // with the shortfall named per subtest.
        $incomplete = $packages
            ->filter(fn (ExamPackage $package): bool => ! PackageComposition::isComplete($package))
            ->map(fn (ExamPackage $package): array => [
                'package' => $package,
                'report' => PackageComposition::report($package),
                'shortfall' => PackageComposition::shortfallMessage($package),
            ])
            ->values();

        return view('admin.dashboard', [
            'site' => $content->site(),
            'title' => 'Ringkasan',
            'questionsPerCategory' => collect(QuestionCategory::cases())
                ->mapWithKeys(fn (QuestionCategory $category): array => [
                    $category->label() => Question::query()->category($category)->count(),
                ]),
            'questionTotal' => Question::query()->count(),
            'draftPackages' => $packages->where('status', ExamPackage::STATUS_DRAFT)->count(),
            'publishedPackages' => $packages->where('status', ExamPackage::STATUS_PUBLISHED)->count(),
            'participantCount' => User::query()->where('role', 'peserta')->count(),
            'attemptCount' => ExamAttempt::query()->count(),
            'incomplete' => $incomplete,
        ]);
    }
}
