<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Admin\Http\Middleware\ShareVelmMenuContext;
use Velm\Admin\Pages\FileLibraryPage;
use Velm\Admin\Pages\FilePropertiesPage;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\FileManagerController;

Route::middleware([
    'web',
    'auth',
    BindVelmEnvironment::class,
    ShareVelmMenuContext::class,
])
    ->prefix('web/files')
    ->group(function (): void {
        Route::livewire('library', FileLibraryPage::class)->name('velm.files.library');
        Route::get('tree', [FileManagerController::class, 'tree'])->name('velm.files.tree');
        Route::get('picker', [FileManagerController::class, 'picker'])->name('velm.files.picker');
        Route::get('picker/browse', [FileManagerController::class, 'pickerBrowse'])->name('velm.files.picker.browse');
        Route::post('picker/upload', [FileManagerController::class, 'pickerUpload'])->name('velm.files.picker.upload');
        Route::livewire('{attId}/properties', FilePropertiesPage::class)
            ->whereNumber('attId')
            ->name('velm.files.properties');
        Route::get('{attId}/properties_panel', [FileManagerController::class, 'propertiesPanel'])
            ->whereNumber('attId')
            ->name('velm.files.properties.panel');
        Route::post('folders', [FileManagerController::class, 'createFolder'])->name('velm.files.folders.create');
        Route::patch('folders/{folderId}', [FileManagerController::class, 'updateFolder'])
            ->whereNumber('folderId')
            ->name('velm.files.folders.update');
        Route::delete('folders/{folderId}', [FileManagerController::class, 'deleteFolder'])
            ->whereNumber('folderId')
            ->name('velm.files.folders.delete');
        Route::post('move', [FileManagerController::class, 'move'])->name('velm.files.move');
        Route::post('copy', [FileManagerController::class, 'copy'])->name('velm.files.copy');
        Route::post('bulk/download', [FileManagerController::class, 'bulkDownload'])->name('velm.files.bulk.download');
        Route::post('bulk/delete', [FileManagerController::class, 'bulkDelete'])->name('velm.files.bulk.delete');
        Route::post('bulk/public', [FileManagerController::class, 'bulkPublic'])->name('velm.files.bulk.public');
    });
