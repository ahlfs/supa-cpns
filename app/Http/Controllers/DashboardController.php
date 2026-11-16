<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Models\ExamPackage;
use App\Services\Exam\AttemptManager;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The participant's own page: what is still running, what has been finished, and
 * the analysis for each finished sitting.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, ContentRepository $content, AttemptManager $manager): View
    {
        $manager->expireOverdue($request->user());

        $attempts = $request->user()->attempts()
            ->with('package')
            ->latest('id')
            ->get();

        return view('exam.dashboard', [
            'site' => $content->site(),
            'open' => $attempts->where('status', AttemptStatus::InProgress),
            'finished' => $attempts->where('status', '!=', AttemptStatus::InProgress),
            // The same map the sibling list (Exam\PackageController::index) builds.
            // Without it each card is told "no open sitting" while the block above
            // it says the opposite about the same package, so the page states two
            // different things about one row and both look deliberate.
            'openAttempts' => $attempts
                ->where('status', AttemptStatus::InProgress)
                ->keyBy('exam_package_id'),
            'packages' => ExamPackage::query()
                ->where('status', ExamPackage::STATUS_PUBLISHED)
                ->orderBy('title')
                ->get(),
        ] + $content->chrome());
    }
}
