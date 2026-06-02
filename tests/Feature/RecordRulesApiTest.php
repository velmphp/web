<?php

declare(strict_types=1);

use Velm\Environment;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }
});

test('get api records applies record rules for bound environment', function (): void {
    $baseEnv = app(Environment::class);
    $hiddenId = $baseEnv->withAclBypass(function () use ($baseEnv): int {
        $baseEnv->model('res.partner')->create(['name' => 'Listed']);
        $hidden = $baseEnv->model('res.partner')->create(['name' => 'Not listed']);
        $baseEnv->model('ir.model.access')->create([
            'name' => 'User/partner read',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
        ]);
        $baseEnv->model('ir.rule')->create([
            'name' => 'Listed only',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
            'domain' => json_encode([['name', '=', 'Listed']]),
        ]);

        return $hidden->ids()[0];
    });

    $baseEnv->withAclBypass(fn () => $baseEnv->model('res.users')->create([
        'name' => 'Limited',
        'login' => 'limited',
    ]));
    $limitedId = $baseEnv->model('res.users')->search([['login', '=', 'limited']])->ids()[0];
    $limitedEnv = new Environment($baseEnv->connection, $baseEnv->registry, uid: $limitedId);
    $this->instance(Environment::class, $limitedEnv);

    $response = $this->getJson('/api/records?model=res.partner&fields=name');

    $response->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('records.0.name', 'Listed');

    expect($hiddenId)->toBeGreaterThan(0);
});

test('get api records with domain still applies record rules', function (): void {
    $baseEnv = app(Environment::class);
    $baseEnv->withAclBypass(function () use ($baseEnv): void {
        $baseEnv->model('res.partner')->create(['name' => 'Alpha', 'active' => true]);
        $baseEnv->model('res.partner')->create(['name' => 'Beta', 'active' => true]);
        $baseEnv->model('res.partner')->create(['name' => 'Alpha', 'active' => false]);
        $baseEnv->model('ir.model.access')->create([
            'name' => 'User/partner read',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
        ]);
        $baseEnv->model('ir.rule')->create([
            'name' => 'Active only',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
            'domain' => json_encode([['active', '=', true]]),
        ]);
    });

    $baseEnv->withAclBypass(fn () => $baseEnv->model('res.users')->create([
        'name' => 'Limited',
        'login' => 'limited2',
    ]));
    $limitedId = $baseEnv->model('res.users')->search([['login', '=', 'limited2']])->ids()[0];
    $limitedEnv = new Environment($baseEnv->connection, $baseEnv->registry, uid: $limitedId);
    $this->instance(Environment::class, $limitedEnv);

    $domain = urlencode(json_encode([['name', '=', 'Alpha']], JSON_THROW_ON_ERROR));
    $response = $this->getJson("/api/records?model=res.partner&domain={$domain}&fields=name,active");

    $response->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('records.0.name', 'Alpha')
        ->assertJsonPath('records.0.active', true);
});

test('delete api records returns 404 when id is hidden by record rule', function (): void {
    $baseEnv = app(Environment::class);
    $hiddenId = $baseEnv->withAclBypass(function () use ($baseEnv): int {
        $baseEnv->model('res.partner')->create(['name' => 'Visible']);
        $hidden = $baseEnv->model('res.partner')->create(['name' => 'Hidden']);
        $baseEnv->model('ir.model.access')->create([
            'name' => 'User/partner read+unlink',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
            'perm_unlink' => true,
        ]);
        $baseEnv->model('ir.rule')->create([
            'name' => 'Visible only',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
            'domain' => json_encode([['name', '=', 'Visible']]),
        ]);

        return $hidden->ids()[0];
    });

    $baseEnv->withAclBypass(fn () => $baseEnv->model('res.users')->create([
        'name' => 'Limited',
        'login' => 'limited3',
    ]));
    $limitedId = $baseEnv->model('res.users')->search([['login', '=', 'limited3']])->ids()[0];
    $limitedEnv = new Environment($baseEnv->connection, $baseEnv->registry, uid: $limitedId);
    $this->instance(Environment::class, $limitedEnv);

    $this->deleteJson('/api/records/'.$hiddenId.'?model=res.partner')
        ->assertNotFound();
});
