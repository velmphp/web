<?php

declare(strict_types=1);

use Velm\Framework\VelmManager;
use Velm\Views\ViewRegistry;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }
});

test('get api views returns resolved arch json', function (): void {
    $env = app(\Velm\Environment::class);
    $expected = app(ViewRegistry::class)->apiPayload($env, 'partners', 'partner.list');

    $response = $this->getJson('/api/views/partners/partner.list');

    $response->assertOk()
        ->assertJson([
            'module' => 'partners',
            'name' => 'partner.list',
            'model' => 'res.partner',
            'view_type' => 'list',
        ])
        ->assertJsonStructure(['id', 'module', 'name', 'model', 'view_type', 'arch']);

    expect($response->json('arch'))->toBe($expected['arch']);
});

test('api arch matches registry arch used by filament pages', function (): void {
    $env = app(\Velm\Environment::class);
    $registry = app(ViewRegistry::class);
    $apiArch = $registry->apiPayload($env, 'partners', 'partner.list')['arch'];
    $pageArch = $registry->arch($env, 'partners', 'partner.list');

    expect($apiArch['fields'])->toBe($pageArch['fields']);
});

test('get api views applies view inheritance chain', function (): void {
    $manager = app(VelmManager::class);
    $manager->install('partners_ext');

    $response = $this->getJson('/api/views/partners/partner.list');

    $response->assertOk();

    $nameField = collect($response->json('arch.fields'))->firstWhere('name', 'name');

    expect($nameField['label'] ?? null)->toBe('Partner name');
});

test('get api views returns 404 for unknown view', function (): void {
    $this->getJson('/api/views/partners/missing.view')
        ->assertNotFound()
        ->assertJsonPath('message', 'View partners.missing.view was not found.');
});
