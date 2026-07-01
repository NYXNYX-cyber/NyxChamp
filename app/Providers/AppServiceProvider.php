<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use App\Events\NewCompetitionDetected;
use App\Listeners\LogNewCompetition;
use Illuminate\Support\Facades\Event;
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
        Event::listen(
            NewCompetitionDetected::class,
            LogNewCompetition::class
        );
    }
}
