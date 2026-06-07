<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Framework\VelmManager;
use Velm\Modules\Tests\Support\ModuleRoots;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    config(['velm.addon_paths' => ModuleRoots::forTests()]);

    $manager = app(VelmManager::class);
    $manager->install('mail');
    $manager->install('change_management');

    $this->actingAs(new GenericUser(['id' => 1, 'email' => 'admin@test']));
});

test('mail thread api requires res_model and res_id', function (): void {
    $this->getJson('/web/mail/thread')->assertStatus(400);
});

test('mail thread api returns context and accepts messages', function (): void {
    $env = app(\Velm\Environment::class);
    $changeId = $env->model('it.change')->create([
        'name' => 'API change',
        'change_type' => 'standard',
        'priority' => '2',
        'risk_level' => 'low',
    ])->ids()[0];

    $this->getJson('/web/mail/thread?res_model=it.change&res_id='.$changeId)
        ->assertOk()
        ->assertJsonPath('has_thread', true);

    $this->postJson('/web/mail/messages', [
        'res_model' => 'it.change',
        'res_id' => $changeId,
        'body' => 'Posted via API',
    ])->assertOk()->assertJsonPath('ok', true);

    $this->postJson('/web/mail/follow', [
        'res_model' => 'it.change',
        'res_id' => $changeId,
        'follow' => true,
    ])->assertOk()->assertJsonPath('ok', true);
});

test('mail thread api returns has_thread false for unsupported model', function (): void {
    $this->getJson('/web/mail/thread?res_model=res.partner&res_id=1')
        ->assertOk()
        ->assertJsonPath('has_thread', false);
});

test('mail thread post message validates body and parameters', function (): void {
    $this->postJson('/web/mail/messages', ['res_model' => '', 'res_id' => 0])
        ->assertStatus(400);

    $env = app(\Velm\Environment::class);
    $changeId = $env->model('it.change')->create([
        'name' => 'Validation change',
        'change_type' => 'standard',
        'priority' => '2',
        'risk_level' => 'low',
    ])->ids()[0];

    $this->postJson('/web/mail/messages', [
        'res_model' => 'it.change',
        'res_id' => $changeId,
        'body' => '   ',
    ])->assertStatus(400);
});

test('mail thread follow validates parameters', function (): void {
    $this->postJson('/web/mail/follow', ['res_model' => 'it.change', 'res_id' => 0])
        ->assertStatus(400);
});

test('mail thread post message returns 403 when mail create access is denied', function (): void {
    $baseEnv = app(\Velm\Environment::class);
    $changeId = $baseEnv->model('it.change')->create([
        'name' => 'Denied post',
        'change_type' => 'standard',
        'priority' => '2',
        'risk_level' => 'low',
    ])->ids()[0];

    $baseEnv->withAclBypass(function () use ($baseEnv): void {
        $baseEnv->model('ir.model.access')->create([
            'name' => 'Read change only',
            'model' => 'it.change',
            'group_id' => null,
            'perm_read' => true,
        ]);
        $baseEnv->model('res.users')->create(['name' => 'No mail post', 'email' => 'nomailpost@test']);
    });
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'nomailpost@test']])->ids()[0];
    $this->instance(\Velm\Environment::class, new \Velm\Environment($baseEnv->connection, $baseEnv->registry, uid: $uid));
    $this->actingAs(new GenericUser(['id' => $uid, 'email' => 'nomailpost@test']));

    $this->postJson('/web/mail/messages', [
        'res_model' => 'it.change',
        'res_id' => $changeId,
        'body' => 'Should fail',
    ])->assertStatus(403);
});

test('mail thread follow returns 403 when follower create access is denied', function (): void {
    $baseEnv = app(\Velm\Environment::class);
    $changeId = $baseEnv->model('it.change')->create([
        'name' => 'Denied follow',
        'change_type' => 'standard',
        'priority' => '2',
        'risk_level' => 'low',
    ])->ids()[0];

    $baseEnv->withAclBypass(function () use ($baseEnv): void {
        $baseEnv->model('ir.model.access')->create([
            'name' => 'Read change only follow',
            'model' => 'it.change',
            'group_id' => null,
            'perm_read' => true,
        ]);
        $baseEnv->model('res.users')->create(['name' => 'No follow', 'email' => 'nofollow@test']);
    });
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'nofollow@test']])->ids()[0];
    $this->instance(\Velm\Environment::class, new \Velm\Environment($baseEnv->connection, $baseEnv->registry, uid: $uid));
    $this->actingAs(new GenericUser(['id' => $uid, 'email' => 'nofollow@test']));

    $this->postJson('/web/mail/follow', [
        'res_model' => 'it.change',
        'res_id' => $changeId,
        'follow' => true,
    ])->assertStatus(403);
});

test('mail thread follow returns 400 for unsupported model', function (): void {
    $this->actingAs(new GenericUser(['id' => 1, 'email' => 'admin@test']));

    $this->postJson('/web/mail/follow', [
        'res_model' => 'res.partner',
        'res_id' => 1,
        'follow' => true,
    ])->assertStatus(400);
});
