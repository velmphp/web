<?php

declare(strict_types=1);

use Velm\Web\Api\DomainParser;
use Velm\Web\Api\InvalidDomainException;

test('domain parser accepts empty and array json', function (): void {
    $parser = new DomainParser;

    expect($parser->parse(''))->toBe([])
        ->and($parser->parse('[]'))->toBe([])
        ->and($parser->parse('[["name","=","Acme"]]'))->toBe([['name', '=', 'Acme']]);
});

test('domain parser rejects non-array json values', function (): void {
    expect(fn () => (new DomainParser)->parse('"not-an-array"'))
        ->toThrow(InvalidDomainException::class, 'Domain must be a JSON array.');
});

test('domain parser rejects invalid json', function (): void {
    expect(fn () => (new DomainParser)->parse('not-json'))
        ->toThrow(InvalidDomainException::class, 'Invalid domain JSON');
});
