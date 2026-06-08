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

test('inline view action form returns not found for unknown action', function (): void {
    $this->get('/web/view-actions/partners/partner.list/page/missing-action/form')
        ->assertNotFound()
        ->assertJson(['message' => 'Action not found.']);
});

test('inline view action form rejects empty json submit body', function (): void {
    $this->postJson('/web/view-actions/partners/partner.list/page/quick-add/form', [])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Request body must be a JSON object.']);
});

test('inline view action form edit url includes record query on detail header action', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Render Me'])->ids()[0];

    $this->get('/web/view-actions/partners/partner.detail/header/quick-edit/form?record='.$id)
        ->assertOk()
        ->assertSee('Render Me', false);
});

test('inline view action form returns forbidden without access', function (): void {
    $env = app(\Velm\Environment::class);
    $groupId = (int) $env->model('res.groups')->search([['name', '=', 'Public']], limit: 1)->ids()[0];
    $userId = (int) $env->model('res.users')->create([
        'name' => 'No Inline Form',
        'email' => 'noform@velm.test',
        'password' => 'secret',
        'group_ids' => [$groupId],
    ])->ids()[0];

    app()->instance(\Velm\Environment::class, new \Velm\Environment(
        $env->connection,
        $env->registry,
        $userId,
    ));

    $this->actingAs(new GenericUser(['id' => $userId, 'email' => 'noform@velm.test']))
        ->get('/web/view-actions/partners/partner.list/page/quick-add/form')
        ->assertForbidden();
});

test('inline view action form returns not found when action has no inline form', function (): void {
    $env = app(\Velm\Environment::class);
    $view = $env->model('ir.ui.view')->search([
        ['module', '=', 'partners'],
        ['name', '=', 'partner.list'],
    ]);
    $row = $view->read()[0];
    $arch = json_decode((string) $row['arch'], true, flags: JSON_THROW_ON_ERROR);
    $arch['page_actions'] = [
        ['label' => 'Export only', 'url' => '/web/demo/partners/export'],
    ];
    $view->write(['arch' => json_encode($arch, JSON_THROW_ON_ERROR)]);

    $this->get('/web/view-actions/partners/partner.list/page/export-only/form')
        ->assertNotFound()
        ->assertJson(['message' => 'Action has no inline form.']);
});

test('inline view action form submit rejects actions without inline form or model', function (): void {
    $env = app(\Velm\Environment::class);
    $view = $env->model('ir.ui.view')->search([
        ['module', '=', 'partners'],
        ['name', '=', 'partner.list'],
    ]);
    $row = $view->read()[0];
    $arch = json_decode((string) $row['arch'], true, flags: JSON_THROW_ON_ERROR);
    $arch['page_actions'] = [
        ['label' => 'Export only', 'url' => '/web/demo/partners/export'],
    ];
    $view->write(['arch' => json_encode($arch, JSON_THROW_ON_ERROR)]);

    $this->postJson('/web/view-actions/partners/partner.list/page/export-only/form', [
        'name' => 'Ignored',
    ])->assertNotFound()
        ->assertJson(['message' => 'Action has no inline form.']);

    $method = new ReflectionMethod(\Velm\Web\Http\Controllers\ViewActionFormController::class, 'assertActionAccess');
    $method->setAccessible(true);
    $controller = new \Velm\Web\Http\Controllers\ViewActionFormController;

    expect(fn () => $method->invoke($controller, $env, ['perm' => 'write'], 'write'))
        ->toThrow(\Velm\Exception\AccessDeniedException::class, 'Action model is missing.');
});

test('inline view action form submit returns not found and forbidden for invalid actions', function (): void {
    $env = app(\Velm\Environment::class);
    $groupId = (int) $env->model('res.groups')->search([['name', '=', 'Public']], limit: 1)->ids()[0];
    $userId = (int) $env->model('res.users')->create([
        'name' => 'No Inline Submit',
        'email' => 'nosubmit@velm.test',
        'password' => 'secret',
        'group_ids' => [$groupId],
    ])->ids()[0];
    $recordId = (int) $env->model('res.partner')->create(['name' => 'Protected'])->ids()[0];

    $this->postJson('/web/view-actions/partners/partner.list/page/missing-action/form', [
        'name' => 'Ignored',
    ])->assertNotFound();

    app()->instance(\Velm\Environment::class, new \Velm\Environment(
        $env->connection,
        $env->registry,
        $userId,
    ));

    $this->actingAs(new GenericUser(['id' => $userId, 'email' => 'nosubmit@velm.test']))
        ->postJson('/web/view-actions/partners/partner.detail/header/quick-edit/form?record='.$recordId, [
            'name' => 'Denied edit',
            'active' => true,
        ])
        ->assertForbidden();
});
