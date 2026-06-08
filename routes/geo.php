<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\GeoImportController;

Route::middleware(['web', 'auth', BindVelmEnvironment::class])
    ->prefix('web/geo')
    ->group(function (): void {
        Route::post('import', [GeoImportController::class, 'import'])
            ->name('velm.geo.import');
    });
