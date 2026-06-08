<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\ViewActionsController;

Route::middleware(['web', 'auth', BindVelmEnvironment::class])
    ->prefix('web/demo')
    ->group(function (): void {
        Route::post('partners/seed', [ViewActionsController::class, 'seedPartners'])
            ->name('velm.demo.partners.seed');
        Route::get('partners/export', [ViewActionsController::class, 'exportPartners'])
            ->name('velm.demo.partners.export');
        Route::get('partners/{id}/export', [ViewActionsController::class, 'exportPartner'])
            ->whereNumber('id')
            ->name('velm.demo.partners.export.one');
        Route::post('partners/{id}/duplicate', [ViewActionsController::class, 'duplicatePartner'])
            ->whereNumber('id')
            ->name('velm.demo.partners.duplicate');
    });
