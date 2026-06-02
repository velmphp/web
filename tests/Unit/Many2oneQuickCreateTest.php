<?php

declare(strict_types=1);

use Velm\Fields\CharField;
use Velm\Models\Model;
use Velm\Web\Api\CannotQuickCreateException;
use Velm\Web\Api\Many2oneQuickCreate;

test('many2one quick create allows models with only name required', function (): void {
    $quickCreate = new Many2oneQuickCreate;

    expect($quickCreate->canQuickCreate(\Velm\Modules\Partners\Models\Country::class))->toBeTrue();
});

test('many2one quick create rejects models with extra required fields', function (): void {
    $quickCreate = new Many2oneQuickCreate;

    $modelClass = new class extends Model
    {
        protected static ?string $name = 'test.quick';

        protected static ?string $table = 'test_quick';

        public static function defineFields(): array
        {
            return [
                'name' => CharField::make()->required(),
                'code' => CharField::make()->required(),
            ];
        }
    };

    expect($quickCreate->canQuickCreate($modelClass::class))->toBeFalse();

    expect(fn () => $quickCreate->assertQuickCreatable('test.quick', $modelClass::fields()))
        ->toThrow(CannotQuickCreateException::class);
});
