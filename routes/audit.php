<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\AuditExportController;

Route::middleware(['web', 'auth', BindVelmEnvironment::class])
    ->prefix('web/audit')
    ->group(function (): void {
        Route::get('logs/export', [AuditExportController::class, 'exportAuditLogs']);
        Route::get('logins/export', [AuditExportController::class, 'exportLoginLogs']);
        Route::get('lifecycle/export', [AuditExportController::class, 'exportUserLifecycle']);
    });
