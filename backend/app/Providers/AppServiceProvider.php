<?php

namespace App\Providers;

use App\Models\User;
use App\Support\School;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // "students.view" jaisi permissions (jin me dot ho) config/roles.php se check hoti hain.
        // Isi se routes me ->middleware('can:students.view') aur Blade me @can('fees.view') chalte hain.
        Gate::before(function (User $user, string $ability) {
            return str_contains($ability, '.') ? $user->hasPermission($ability) : null;
        });

        // School ka naam/logo/currency in pages ko Settings se milta hai ($school variable)
        View::composer(['layouts.app', 'layouts.sidebar', 'layouts.guest', 'welcome'], function ($view) {
            $view->with('school', School::info());
        });
    }
}
