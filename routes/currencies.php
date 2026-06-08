<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\CurrencyImportController;

Route::middleware(['web', 'auth', BindVelmEnvironment::class])
    ->prefix('web/currencies')
    ->group(function (): void {
        Route::post('import', [CurrencyImportController::class, 'import'])
            ->name('velm.currencies.import');
    });
