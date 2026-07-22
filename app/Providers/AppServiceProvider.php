<?php

namespace App\Providers;

use App\Models\AbsenceRequest;
use App\Models\AppNotification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        Route::bind('demande', fn ($value) => AbsenceRequest::query()->findOrFail($value));
        Route::bind('notification', fn ($value) => AppNotification::query()->findOrFail($value));
    }
}
