<?php

declare(strict_types=1);

use Velm\Environment;
use Velm\Modules\ModuleInstaller;
use Velm\Modules\Tests\TestCase;
use Velm\Web\Api\RecordSerializer;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    $roots = [dirname(__DIR__, 3).'/modules/modules'];
    $installer = new ModuleInstaller;
    $installer->installBootstrap($roots, ['base']);
    $installer->install('partners', $roots);

    $this->env = $installer->environment($roots);
    $this->serializer = new RecordSerializer;
});

test('serializeOne includes many2one id and label', function (): void {
    $countryId = $this->env->model('res.country')->create(['name' => 'Belgium', 'code' => 'BE'])->ids()[0];
    $partnerId = $this->env->model('res.partner')->create([
        'name' => 'Velm SA',
        'country_id' => $countryId,
    ])->ids()[0];

    $row = $this->env->browse('res.partner', [$partnerId])->read(['id', 'name', 'country_id', 'display_name'])[0];

    $out = $this->serializer->serializeOne($this->env, 'res.partner', $row, ['name', 'country_id']);

    expect($out['name'])->toBe('Velm SA')
        ->and($out['country_id'])->toBe([$countryId, 'Belgium']);
});

test('serialize includes display_name when requested', function (): void {
    $id = $this->env->model('res.partner')->create(['name' => 'Display Co'])->ids()[0];
    $row = $this->env->browse('res.partner', [$id])->read(['id', 'name', 'display_name'])[0];

    $out = $this->serializer->serializeOne($this->env, 'res.partner', $row, ['display_name']);

    expect($out['display_name'])->toBe('Display Co');
});

test('serialize throws for unknown field', function (): void {
    $id = $this->env->model('res.partner')->create(['name' => 'X'])->ids()[0];
    $row = $this->env->browse('res.partner', [$id])->read(['id', 'name'])[0];

    expect(fn () => $this->serializer->serializeOne($this->env, 'res.partner', $row, ['nope_field']))
        ->toThrow(\InvalidArgumentException::class);
});

test('serializeMany2one returns id pair when related row missing', function (): void {
    $id = $this->env->model('res.partner')->create([
        'name' => 'Orphan',
        'country_id' => 99999,
    ])->ids()[0];

    $row = $this->env->browse('res.partner', [$id])->read(['id', 'country_id'])[0];

    $out = $this->serializer->serializeOne($this->env, 'res.partner', $row, ['country_id']);

    expect($out['country_id'])->toBe([99999, '99999']);
});
