<?php

declare(strict_types=1);

use Velm\Fields\CharField;
use Velm\Fields\IntegerField;
use Velm\Web\Api\Many2oneSearch;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }
});

test('many2one search resolveTextField prefers name when text-like', function (): void {
    $search = new Many2oneSearch;
    $method = (new ReflectionClass($search))->getMethod('resolveTextField');
    $method->setAccessible(true);

    $field = $method->invoke($search, [
        'name' => CharField::make(),
        'code' => IntegerField::make(),
    ]);

    expect($field)->toBe('name');
});

test('many2one search resolveTextField falls back to other char fields', function (): void {
    $search = new Many2oneSearch;
    $method = (new ReflectionClass($search))->getMethod('resolveTextField');
    $method->setAccessible(true);

    $field = $method->invoke($search, [
        'code' => IntegerField::make(),
        'label' => CharField::make(),
    ]);

    expect($field)->toBe('label');
});

test('many2one search resolveTextField returns null without text-like fields', function (): void {
    $search = new Many2oneSearch;
    $method = (new ReflectionClass($search))->getMethod('resolveTextField');
    $method->setAccessible(true);

    expect($method->invoke($search, ['qty' => IntegerField::make()]))->toBeNull();
});

test('many2one search skips filtering when no text field exists', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.country')->create(['name' => 'ZZ Unique', 'code' => 'ZZ']);

    $results = (new Many2oneSearch)->search($env, 'res.country', '', 50);

    expect(collect($results['results'])->pluck('label'))->toContain('ZZ Unique');
});
