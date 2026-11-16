<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamPackage;
use App\Services\Exam\AttemptManager;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The package list, and the two ways in: start (which resumes an open sitting)
 * and start over (which closes the old one first).
 */
final class PackageController extends Controller
{
    public function index(Request $request, ContentRepository $content, AttemptManager $manager): View
    {
        // The read path closes anything that ran out of time, so the list never
        // shows a stale "still running" sitting.
        $manager->expireOverdue($request->user());

        return view('exam.index', [
            'site' => $content->site(),
            'packages' => ExamPackage::query()
                ->where('status', ExamPackage::STATUS_PUBLISHED)
                ->orderBy('title')
                ->get(),
            'openAttempts' => $request->user()->attempts()
                ->where('status', AttemptStatus::InProgress)
                ->get()
                ->keyBy('exam_package_id'),
        ] + $content->chrome());
    }

    public function start(Request $request, ExamPackage $package, AttemptManager $manager): RedirectResponse
    {
        abort_unless($package->isPublished(), 404);

        // Starting is resuming: someone who closed the browser on item 40 comes
        // back to item 40 with the clock still running. A sitting that expired
        // behind their back was closed by the sweep above, so this is a fresh
        // paper only when there is nothing left to resume — and the click that
        // got them here said "start", not "check my score".
        $attempt = $manager->startOrResume($request->user(), $package);

        return redirect()->route('tryout.room', $attempt);
    }

    public function restart(Request $request, ExamPackage $package, AttemptManager $manager): RedirectResponse
    {
        abort_unless($package->isPublished(), 404);

        $attempt = $manager->restart($request->user(), $package);

        return redirect()
            ->route('tryout.room', $attempt)
            ->with('status', 'Sesi sebelumnya ditutup dan dinilai sebagai waktu habis.');
    }
}
