<?php

declare(strict_types=1);

use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }
});

test('get api records returns partner rows', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.partner')->create(['name' => 'Acme', 'active' => true]);
    $env->model('res.partner')->create(['name' => 'Beta', 'active' => false]);

    $response = $this->getJson('/api/records?model=res.partner&fields=name,active');

    $response->assertOk()
        ->assertJsonPath('model', 'res.partner');

    $names = collect($response->json('records'))->pluck('name')->all();

    expect($names)->toContain('Acme', 'Beta')
        ->and($response->json('count'))->toBeGreaterThanOrEqual(2);
});

test('get api records filters with domain json', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.partner')->create(['name' => 'Active Co', 'active' => true]);
    $env->model('res.partner')->create(['name' => 'Inactive Co', 'active' => false]);

    $domain = urlencode(json_encode([
        ['active', '=', true],
        ['name', 'in', ['Active Co']],
    ], JSON_THROW_ON_ERROR));

    $response = $this->getJson("/api/records?model=res.partner&domain={$domain}&fields=name");

    $response->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('records.0.name', 'Active Co');
});

test('get api records serializes many2one as id and label', function (): void {
    $env = app(\Velm\Environment::class);
    $countryId = $env->model('res.country')->create(['name' => 'API Test Country', 'code' => 'AT'])->ids()[0];
    $env->model('res.partner')->create(['name' => 'API Test Partner', 'country_id' => $countryId]);

    $response = $this->getJson('/api/records?model=res.partner&fields=name,country_id&domain='.urlencode(json_encode([
        ['name', '=', 'API Test Partner'],
    ], JSON_THROW_ON_ERROR)));

    $response->assertOk()
        ->assertJsonPath('records.0.country_id', [$countryId, 'API Test Country']);
});

test('get api records returns 404 for unknown model', function (): void {
    $this->getJson('/api/records?model=no.such')
        ->assertNotFound()
        ->assertJsonPath('message', 'Unknown model no.such.');
});

test('get api records returns 400 for invalid domain json', function (): void {
    $this->getJson('/api/records?model=res.partner&domain=not-json')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Invalid domain JSON: Syntax error');
});

test('get api records returns 400 for unknown field', function (): void {
    $this->getJson('/api/records?model=res.partner&fields=nope')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Unknown field nope on res.partner.');
});

test('get api records requires model query parameter', function (): void {
    $this->getJson('/api/records')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Query parameter model is required.');
});

test('post api records creates a partner', function (): void {
    $response = $this->postJson('/api/records?model=res.partner', [
        'name' => 'New Partner',
        'active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'New Partner')
        ->assertJsonPath('active', true);

    expect($response->json('id'))->toBeInt();
});

test('post api records accepts many2one as id pair', function (): void {
    $env = app(\Velm\Environment::class);
    $countryId = $env->model('res.country')->create(['name' => 'France', 'code' => 'FR'])->ids()[0];

    $response = $this->postJson('/api/records?model=res.partner', [
        'name' => 'Paris Co',
        'country_id' => [$countryId, 'France'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('country_id', [$countryId, 'France']);
});

test('patch api records updates a partner', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Before'])->ids()[0];

    $response = $this->patchJson("/api/records/{$id}?model=res.partner", [
        'name' => 'After',
    ]);

    $response->assertOk()
        ->assertJsonPath('id', $id)
        ->assertJsonPath('name', 'After');
});

test('patch api records returns 404 for missing record', function (): void {
    $this->patchJson('/api/records/999?model=res.partner', ['name' => 'Nope'])
        ->assertNotFound()
        ->assertJsonPath('message', 'res.partner(999) not found.');
});

test('delete api records removes a partner', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Gone'])->ids()[0];

    $this->deleteJson("/api/records/{$id}?model=res.partner")
        ->assertNoContent();

    expect($env->model('res.partner')->search([['id', '=', $id]])->count())->toBe(0);
});

test('post api records rejects unknown fields', function (): void {
    $this->postJson('/api/records?model=res.partner', ['nope' => 'x'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Unknown field(s) on res.partner: nope');
});

test('post api records rejects empty json body', function (): void {
    $this->postJson('/api/records?model=res.partner', [])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Request body must be a JSON object.');
});

test('patch api records rejects empty json body', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Patch body'])->ids()[0];

    $this->patchJson("/api/records/{$id}?model=res.partner", [])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Request body must be a JSON object.');
});

test('delete api records requires model query parameter', function (): void {
    $this->deleteJson('/api/records/1')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Query parameter model is required.');
});

test('get api records supports offset and order parameters', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.partner')->create(['name' => 'AAA']);
    $env->model('res.partner')->create(['name' => 'ZZZ']);

    $this->getJson('/api/records?model=res.partner&fields=name&order=name desc&offset=0&limit=1')
        ->assertOk()
        ->assertJsonPath('count', 1);
});

test('get api records returns 403 when read access is denied', function (): void {
    $baseEnv = app(\Velm\Environment::class);
    $baseEnv->withAclBypass(fn () => $baseEnv->model('res.users')->create([
        'name' => 'No partner read',
        'email' => 'nopartnerread@test',
    ]));
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'nopartnerread@test']])->ids()[0];
    $this->instance(\Velm\Environment::class, new \Velm\Environment($baseEnv->connection, $baseEnv->registry, uid: $uid));

    $this->getJson('/api/records?model=res.partner&fields=name')
        ->assertStatus(403);
});

test('get api records rejects non-array domain json', function (): void {
    $this->getJson('/api/records?model=res.partner&domain='.urlencode('"not-an-array"'))
        ->assertStatus(400)
        ->assertJsonPath('message', 'Domain must be a JSON array.');
});

test('post api records requires model query parameter', function (): void {
    $this->postJson('/api/records', ['name' => 'Missing model param'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Query parameter model is required.');
});

test('post api records returns 404 for unknown model', function (): void {
    $this->postJson('/api/records?model=no.such', ['name' => 'X'])
        ->assertNotFound();
});

test('post api records returns 403 when create access is denied', function (): void {
    $baseEnv = app(\Velm\Environment::class);
    $baseEnv->withAclBypass(fn () => $baseEnv->model('res.users')->create([
        'name' => 'No partner create',
        'email' => 'nopartnercreate@test',
    ]));
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'nopartnercreate@test']])->ids()[0];
    $this->instance(\Velm\Environment::class, new \Velm\Environment($baseEnv->connection, $baseEnv->registry, uid: $uid));

    $this->postJson('/api/records?model=res.partner', ['name' => 'Denied'])
        ->assertStatus(403);
});

test('patch api records requires model query parameter', function (): void {
    $this->patchJson('/api/records/1', ['name' => 'X'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Query parameter model is required.');
});

test('patch api records returns 403 when write access is denied', function (): void {
    $baseEnv = app(\Velm\Environment::class);
    $id = $baseEnv->model('res.partner')->create(['name' => 'Locked'])->ids()[0];
    $baseEnv->withAclBypass(function () use ($baseEnv): void {
        $baseEnv->model('ir.model.access')->create([
            'name' => 'Partner read only',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
        ]);
        $baseEnv->model('res.users')->create(['name' => 'Read only', 'email' => 'readonly@test']);
    });
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'readonly@test']])->ids()[0];
    $this->instance(\Velm\Environment::class, new \Velm\Environment($baseEnv->connection, $baseEnv->registry, uid: $uid));

    $this->patchJson("/api/records/{$id}?model=res.partner", ['name' => 'Nope'])
        ->assertStatus(403);
});

test('delete api records returns 403 when unlink access is denied', function (): void {
    $baseEnv = app(\Velm\Environment::class);
    $id = $baseEnv->model('res.partner')->create(['name' => 'Protected'])->ids()[0];
    $baseEnv->withAclBypass(function () use ($baseEnv): void {
        $baseEnv->model('ir.model.access')->create([
            'name' => 'Partner read only unlink',
            'model' => 'res.partner',
            'group_id' => null,
            'perm_read' => true,
        ]);
        $baseEnv->model('res.users')->create(['name' => 'Cant delete', 'email' => 'cantdelete@test']);
    });
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'cantdelete@test']])->ids()[0];
    $this->instance(\Velm\Environment::class, new \Velm\Environment($baseEnv->connection, $baseEnv->registry, uid: $uid));

    $this->deleteJson('/api/records/'.$id.'?model=res.partner')
        ->assertStatus(403);
});
