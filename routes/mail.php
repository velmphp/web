<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Admin\Http\Middleware\ShareVelmMenuContext;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\MailThreadController;

Route::middleware([
    'web',
    'auth',
    BindVelmEnvironment::class,
    ShareVelmMenuContext::class,
])
    ->prefix('web/mail')
    ->group(function (): void {
        Route::get('/thread', [MailThreadController::class, 'thread']);
        Route::post('/messages', [MailThreadController::class, 'postMessage']);
        Route::post('/follow', [MailThreadController::class, 'follow']);
    });
