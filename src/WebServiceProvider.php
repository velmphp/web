<?php

declare(strict_types=1);

namespace Velm\Web;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WebServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes/api.php');
    }
}
