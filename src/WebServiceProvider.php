<?php

declare(strict_types=1);

namespace Velm\Web;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;

final class WebServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', BindVelmEnvironment::class])
            ->prefix('api')
            ->group(__DIR__.'/../routes/api.php');

        $this->loadRoutesFrom(__DIR__.'/../routes/files.php');
    }
}
