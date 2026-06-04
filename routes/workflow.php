<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Velm\Admin\Http\Middleware\ShareVelmMenuContext;
use Velm\Admin\Pages\WorkflowBuilderPage;
use Velm\Admin\Pages\WorkflowInboxPage;
use Velm\Framework\Http\Middleware\BindVelmEnvironment;
use Velm\Web\Http\Controllers\WorkflowController;

Route::middleware([
    'web',
    'auth',
    BindVelmEnvironment::class,
    ShareVelmMenuContext::class,
])
    ->prefix('web/workflow')
    ->group(function (): void {
        Route::livewire('inbox', WorkflowInboxPage::class)->name('velm.workflow.inbox');
        Route::livewire('build', WorkflowBuilderPage::class)->name('velm.workflow.build');
        Route::livewire('{workflowId}/build', WorkflowBuilderPage::class)
            ->whereNumber('workflowId')
            ->name('velm.workflow.build.edit');

        Route::get('/context', [WorkflowController::class, 'context']);
        Route::post('/start', [WorkflowController::class, 'start']);
        Route::get('/instances/{instanceId}/transition/{transitionKey}/form', [WorkflowController::class, 'transitionForm']);
        Route::post('/instances/{instanceId}/transition/{transitionKey}', [WorkflowController::class, 'transition']);
        Route::post('/approvals/{approvalId}/act', [WorkflowController::class, 'approve']);

        Route::prefix('api')->group(function (): void {
            Route::get('/models', [WorkflowController::class, 'models']);
            Route::get('/fields', [WorkflowController::class, 'fields']);
            Route::get('/groups', [WorkflowController::class, 'groups']);
            Route::get('/users', [WorkflowController::class, 'users']);
            Route::post('/definitions', [WorkflowController::class, 'storeDefinition']);
            Route::put('/definitions/{workflowId}', [WorkflowController::class, 'updateDefinition'])->whereNumber('workflowId');
        });
    });
