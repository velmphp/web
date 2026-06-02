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
    $env->model('res.country')->create(['name' => 'Belgium', 'code' => 'BE']);
    $env->model('res.country')->create(['name' => 'France', 'code' => 'FR']);

    $response = $this->getJson('/api/m2o/search?model=res.country');

    $response->assertOk()
        ->assertJsonCount(2, 'results')
        ->assertJsonStructure(['results' => [['id', 'label']]]);
});

test('get api m2o search filters by query on name', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.country')->create(['name' => 'Belgium', 'code' => 'BE']);
    $env->model('res.country')->create(['name' => 'France', 'code' => 'FR']);

    $response = $this->getJson('/api/m2o/search?model=res.country&q=bel');

    $response->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.label', 'Belgium');
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
