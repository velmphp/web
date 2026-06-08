<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Modules\Base\CurrencyApiImporter;
use Velm\Modules\Base\CurrencyImportService;
use Velm\Modules\GeoData\GeoHttpGateway;
use Velm\Modules\ModuleInstaller;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    $roots = [dirname(__DIR__, 3).'/modules/modules'];
    $installer = new ModuleInstaller;
    $installer->installBootstrap($roots, ['base', 'admin']);

    $this->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
    ]));
});

test('currency import endpoint imports currencies via api service', function (): void {
    $http = new class implements GeoHttpGateway
    {
        public function get(string $url): array
        {
            if (str_contains($url, 'restcountries.com')) {
                return [
                    ['currencies' => ['EUR' => ['name' => 'Euro', 'symbol' => '€']]],
                    ['currencies' => ['USD' => ['name' => 'United States dollar', 'symbol' => '$']]],
                    ['currencies' => ['GBP' => ['name' => 'British pound', 'symbol' => '£']]],
                ];
            }

            throw new RuntimeException('Unexpected GET '.$url);
        }

        public function post(string $url, array $body): array
        {
            throw new RuntimeException('Unexpected POST '.$url);
        }
    };

    $this->app->instance(CurrencyImportService::class, new CurrencyImportService(new CurrencyApiImporter($http)));

    $response = $this->post('/web/currencies/import')
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($response->json('imported'))->toBe(3);

    $env = app(\Velm\Environment::class);

    expect($env->model('res.currency')->search()->count())->toBe(3)
        ->and($env->model('res.currency')->search([['active', '=', true]])->count())->toBe(1);
});
