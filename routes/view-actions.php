<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\ViewActionFormController;

Route::middleware(['web', 'auth', BindVelmEnvironment::class])
    ->prefix('web/view-actions')
    ->group(function (): void {
        Route::get('{module}/{viewName}/{slot}/{actionKey}/form', [ViewActionFormController::class, 'show'])
            ->name('velm.view-actions.form.show');
        Route::post('{module}/{viewName}/{slot}/{actionKey}/form', [ViewActionFormController::class, 'submit'])
            ->name('velm.view-actions.form.submit');
    });
