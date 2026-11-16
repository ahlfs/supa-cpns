<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BuildPackageRequest;
use App\Models\ExamPackage;
use App\Support\ContentRepository;
use App\Support\PackageComposition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The package list and its own columns. What is *inside* a package — which items,
 * in what order, and whether it is open — belongs to the builder.
 */
final class PackageController extends Controller
{
    public function index(ContentRepository $content): View
    {
        // Counted in the query, and the composition asked of the one class that
        // owns the rule, so the list cannot disagree with the publish gate.
        $packages = ExamPackage::query()->withCount('questions', 'attempts')->orderBy('title')->get();

        return view('admin.packages.index', [
            'site' => $content->site(),
            'title' => 'Paket tryout',
            'packages' => $packages->map(fn (ExamPackage $package): array => [
                'package' => $package,
                'complete' => PackageComposition::isComplete($package),
                'shortfall' => PackageComposition::shortfallMessage($package),
            ]),
        ]);
    }

    public function create(ContentRepository $content): View
    {
        return view('admin.packages.create', [
            'site' => $content->site(),
            'title' => 'Paket baru',
        ]);
    }

    public function store(BuildPackageRequest $request): RedirectResponse
    {
        // A new package is a draft and lands straight in the builder: the reason
        // to create one is to fill it, so that is the next screen, not the list.
        $package = ExamPackage::create($request->payload());

        return redirect()
            ->route('admin.packages.builder', $package)
            ->with('status', 'Paket dibuat. Sekarang isi '.config('cpns.total_items').' soalnya.');
    }

    public function edit(ExamPackage $package, ContentRepository $content): View
    {
        return view('admin.packages.create', [
            'site' => $content->site(),
            'title' => 'Ubah paket',
            'package' => $package,
        ]);
    }

    public function update(BuildPackageRequest $request, ExamPackage $package): RedirectResponse
    {
        // The columns stay editable even on a package that has been sat: renaming
        // a tryout or correcting its description does not touch anyone's score.
        // The composition is what locks, and that is the builder's business.
        $package->update($request->payload());

        return redirect()
            ->route('admin.packages.builder', $package)
            ->with('status', 'Paket diperbarui.');
    }
}
