<?php

declare(strict_types=1);

use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }
});

test('get api m2o search returns id and label pairs', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.country')->create(['name' => 'API Belgium', 'code' => 'AB']);
    $env->model('res.country')->create(['name' => 'API France', 'code' => 'AF']);

    $response = $this->getJson('/api/m2o/search?model=res.country');

    $response->assertOk()
        ->assertJsonStructure(['results' => [['id', 'label']]]);

    $labels = collect($response->json('results'))->pluck('label')->all();

    expect($labels)->toContain('API Belgium', 'API France');
});

test('get api m2o search filters by query on name', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.country')->create(['name' => 'API Belgium', 'code' => 'AB']);
    $env->model('res.country')->create(['name' => 'API France', 'code' => 'AF']);

    $response = $this->getJson('/api/m2o/search?'.http_build_query([
        'model' => 'res.country',
        'q' => 'API bel',
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.label', 'API Belgium');
});

test('get api m2o search respects limit', function (): void {
    $env = app(\Velm\Environment::class);
    foreach (['A', 'B', 'C'] as $name) {
        $env->model('res.country')->create(['name' => $name, 'code' => strtoupper($name)]);
    }

    $this->getJson('/api/m2o/search?model=res.country&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'results');
});

test('get api m2o search returns 404 for unknown model', function (): void {
    $this->getJson('/api/m2o/search?model=no.such')
        ->assertNotFound()
        ->assertJsonPath('message', 'Unknown model no.such.');
});

test('get api m2o search requires model query parameter', function (): void {
    $this->getJson('/api/m2o/search')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Query parameter model is required.');
});

test('post api m2o quick-create creates a country by name', function (): void {
    $response = $this->postJson('/api/m2o/quick-create', [
        'model' => 'res.country',
        'name' => 'Luxembourg',
    ]);

    $response->assertCreated()
        ->assertJsonPath('label', 'Luxembourg')
        ->assertJsonStructure(['id', 'label']);

    $id = (int) $response->json('id');
    $env = app(\Velm\Environment::class);

    expect($env->browse('res.country', [$id])->read()[0]['name'])->toBe('Luxembourg');
});

test('post api m2o quick-create returns 400 when name is missing', function (): void {
    $this->postJson('/api/m2o/quick-create', [
        'model' => 'res.country',
        'name' => '   ',
    ])
        ->assertStatus(400)
        ->assertJsonPath('message', "Missing 'name'.");
});

test('post api m2o quick-create returns 404 for unknown model', function (): void {
    $this->postJson('/api/m2o/quick-create', [
        'model' => 'no.such',
        'name' => 'Test',
    ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Unknown model no.such.');
});

test('post api m2o quick-create requires model in body', function (): void {
    $this->postJson('/api/m2o/quick-create', [
        'name' => 'Test',
    ])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Body field model is required.');
});
