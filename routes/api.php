<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Web\Http\Controllers\ViewController;

Route::get('views/{module}/{name}', ViewController::class)
    ->name('velm.api.views.show');
