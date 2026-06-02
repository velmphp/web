<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Web\Http\Controllers\Many2oneController;
use Velm\Web\Http\Controllers\RecordController;
use Velm\Web\Http\Controllers\ViewController;

Route::get('views/{module}/{name}', ViewController::class)
    ->name('velm.api.views.show');

Route::get('records', [RecordController::class, 'index'])
    ->name('velm.api.records.index');
Route::post('records', [RecordController::class, 'store'])
    ->name('velm.api.records.store');
Route::patch('records/{recordId}', [RecordController::class, 'update'])
    ->whereNumber('recordId')
    ->name('velm.api.records.update');
Route::delete('records/{recordId}', [RecordController::class, 'destroy'])
    ->whereNumber('recordId')
    ->name('velm.api.records.destroy');

Route::get('m2o/search', [Many2oneController::class, 'search'])
    ->name('velm.api.m2o.search');
