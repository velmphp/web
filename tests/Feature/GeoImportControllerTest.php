<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Modules\GeoData\GeoApiImporter;
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
    $installer->install('geo_data', $roots);

    $this->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
    ]));
});

test('geo import endpoint imports geography via api importer', function (): void {
    $http = new class implements GeoHttpGateway
    {
        public function get(string $url): array
        {
            if (str_contains($url, 'restcountries.com')) {
                return [
                    [
                        'cca2' => 'BE',
                        'cca3' => 'BEL',
                        'name' => ['common' => 'Belgium'],
                        'capital' => ['Brussels'],
                        'population' => 11825551,
                        'continents' => ['Europe'],
                        'idd' => ['root' => '+3', 'suffixes' => ['2']],
                        'currencies' => ['EUR' => ['name' => 'Euro', 'symbol' => '€']],
                        'flag' => '🇧🇪',
                    ],
                ];
            }

            return [
                'error' => false,
                'data' => [
                    [
                        'name' => 'Belgium',
                        'iso2' => 'BE',
                        'states' => [
                            ['name' => 'Flanders', 'state_code' => 'VLG'],
                        ],
                    ],
                ],
            ];
        }

        public function post(string $url, array $body): array
        {
            return [
                'error' => false,
                'data' => ['Brussels', 'Ghent'],
            ];
        }
    };

    $this->app->instance(GeoApiImporter::class, new GeoApiImporter($http));

    $response = $this->post('/web/geo/import')
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($response->json('counts'))->toBe([
        'countries' => 1,
        'states' => 1,
        'cities' => 2,
    ]);

    $env = app(\Velm\Environment::class);

    expect($env->model('res.city')->search()->count())->toBe(2);
});
