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
        ->assertJsonPath('model', 'res.partner')
        ->assertJsonPath('count', 2)
        ->assertJsonCount(2, 'records');

    expect(collect($response->json('records'))->pluck('name')->all())
        ->toContain('Acme', 'Beta');
});

test('get api records filters with domain json', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.partner')->create(['name' => 'Active Co', 'active' => true]);
    $env->model('res.partner')->create(['name' => 'Inactive Co', 'active' => false]);

    $domain = urlencode(json_encode([['active', '=', true]], JSON_THROW_ON_ERROR));

    $response = $this->getJson("/api/records?model=res.partner&domain={$domain}&fields=name");

    $response->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('records.0.name', 'Active Co');
});

test('get api records serializes many2one as id and label', function (): void {
    $env = app(\Velm\Environment::class);
    $countryId = $env->model('res.country')->create(['name' => 'Belgium', 'code' => 'BE'])->ids()[0];
    $env->model('res.partner')->create(['name' => 'Velm SA', 'country_id' => $countryId]);

    $response = $this->getJson('/api/records?model=res.partner&fields=name,country_id');

    $response->assertOk()
        ->assertJsonPath('records.0.country_id', [$countryId, 'Belgium']);
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
