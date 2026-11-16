<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;

/**
 * The public landing page. It renders the home content sections in order, each
 * section receiving its own slice of resources/content/home.
 */
final class HomeController extends Controller
{
    public function __invoke(ContentRepository $content): View
    {
        $sections = $content->page('home');

        return view('home', ['site' => $content->site()] + $sections);
    }
}
