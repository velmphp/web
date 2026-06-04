<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Modules\ModuleInstaller;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    $roots = [dirname(__DIR__, 3).'/modules/modules'];
    $installer = new ModuleInstaller;
    $installer->installBootstrap($roots, ['base', 'admin']);
    $installer->install('workflow', $roots);

    $this->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
    ]));
});

test('workflow inbox renders inside the velm shell with workflow navigation', function (): void {
    $this->get('/web/workflow/inbox')
        ->assertOk()
        ->assertSee('My approvals', false)
        ->assertSee('Design workflow', false)
        ->assertSee('velm-shell', false)
        ->assertSee('pv-sidebar-nav', false)
        ->assertDontSee('Back to Velm', false);
});

test('workflow builder renders inside the velm shell', function (): void {
    $this->get('/web/workflow/build')
        ->assertOk()
        ->assertSee('pvWorkflowBuilder', false)
        ->assertSee('velm-shell', false)
        ->assertSee('Design workflow', false);
});
