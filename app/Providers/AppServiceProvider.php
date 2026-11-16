<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\ContentRepository;
use App\Support\QuestionBank;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Content lives in resources/content as plain PHP arrays; one repository
        // instance serves the request.
        $this->app->singleton(
            ContentRepository::class,
            static fn (): ContentRepository => new ContentRepository(resource_path('content')),
        );

        // The authored question bank lives beside the marketing copy, one file per
        // subtest, and is read the same way.
        $this->app->singleton(
            QuestionBank::class,
            static fn (): QuestionBank => new QuestionBank(resource_path('content/questions')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One line instead of a policy per model: an admin passes everything, and
        // a null return hands the decision to whatever check runs next.
        Gate::before(static fn (User $user): ?bool => $user->isAdmin() ? true : null);
    }
}
