<?php

declare(strict_types=1);

use Velm\Environment;
use Velm\Modules\ModuleInstaller;
use Velm\Modules\Tests\TestCase;
use Velm\Web\Api\RecordValues;

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
    $this->values = new RecordValues;
});

test('record values rejects id and display_name writes', function (): void {
    expect(fn () => $this->values->coerce($this->env, 'res.partner', ['id' => 99]))
        ->toThrow(InvalidArgumentException::class, 'Cannot set id via API.');

    expect(fn () => $this->values->coerce($this->env, 'res.partner', ['display_name' => 'X']))
        ->toThrow(InvalidArgumentException::class, 'Cannot set display_name via API.');
});

test('record values coerces many2one null empty array and scalar', function (): void {
    $countryId = $this->env->model('res.country')->create(['name' => 'Norway', 'code' => 'NO'])->ids()[0];

    expect($this->values->coerce($this->env, 'res.partner', ['country_id' => null]))
        ->toBe(['country_id' => null])
        ->and($this->values->coerce($this->env, 'res.partner', ['country_id' => []]))
        ->toBe(['country_id' => null])
        ->and($this->values->coerce($this->env, 'res.partner', ['country_id' => [$countryId, 'Norway']]))
        ->toBe(['country_id' => $countryId])
        ->and($this->values->coerce($this->env, 'res.partner', ['country_id' => (string) $countryId]))
        ->toBe(['country_id' => $countryId]);
});
