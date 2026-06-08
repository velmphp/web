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
    $installer->installBootstrap($roots, ['base', 'admin', 'partners']);

    $this->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
    ]));
});

test('inline view action form renders html dialog fields', function (): void {
    $this->get('/web/view-actions/partners/partner.list/page/quick-add/form')
        ->assertOk()
        ->assertSee('Quick add', false)
        ->assertSee('name', false)
        ->assertSee('data-velm-inline-action-form', false);
});

test('inline view action form creates partner record', function (): void {
    $response = $this->postJson('/web/view-actions/partners/partner.list/page/quick-add/form', [
        'name' => 'Inline Action Partner',
        'active' => true,
    ]);

    $response->assertOk()->assertJson(['ok' => true]);

    $env = app(\Velm\Environment::class);

    expect($env->model('res.partner')->search([['name', '=', 'Inline Action Partner']])->count())->toBe(1);
});

test('inline view action form updates existing partner', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Before inline edit'])->ids()[0];

    $this->postJson('/web/view-actions/partners/partner.detail/header/quick-edit/form?record='.$id, [
        'name' => 'After inline edit',
        'active' => true,
    ])->assertOk();

    $row = $env->browse('res.partner', [$id])->read(['name'])[0];

    expect($row['name'])->toBe('After inline edit');
});
