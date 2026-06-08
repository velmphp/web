<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Database\PdoConnection;
use Velm\Environment;
use Velm\Modules\GeoData\GeoApiImporter;
use Velm\Modules\GeoData\GeoHttpGateway;
use Velm\Modules\ModuleInstaller;
use Velm\Registry;
use Velm\Schema\SchemaBuilder;
use Velm\Web\Http\Controllers\GeoImportController;
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

test('geo import endpoint returns forbidden without write access', function (): void {
    $env = app(\Velm\Environment::class);
    $groupId = (int) $env->model('res.groups')->search([['name', '=', 'Public']], limit: 1)->ids()[0];
    $userId = (int) $env->model('res.users')->create([
        'name' => 'No Geo Write',
        'email' => 'nogeowrite@velm.test',
        'password' => 'secret',
        'group_ids' => [$groupId],
    ])->ids()[0];

    app()->instance(\Velm\Environment::class, new \Velm\Environment(
        $env->connection,
        $env->registry,
        $userId,
    ));

    $this->actingAs(new GenericUser(['id' => $userId, 'email' => 'nogeowrite@velm.test']))
        ->post('/web/geo/import')
        ->assertForbidden();
});

test('geo import endpoint returns not found when geo models are missing', function (): void {
    $envWithoutGeo = Registry::using(function (Registry $registry): Environment {
        $connection = PdoConnection::sqliteMemory();
        (new SchemaBuilder($connection))->syncRegistry($registry);

        return new Environment($connection, $registry, uid: 1);
    });

    $response = (new GeoImportController)->import($envWithoutGeo, new GeoApiImporter);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['message'])->toBe('Geo data module is not installed.');
});

test('geo import endpoint returns bad gateway when importer throws', function (): void {
    $http = new class implements GeoHttpGateway
    {
        public function get(string $url): array
        {
            throw new RuntimeException('Upstream unavailable');
        }

        public function post(string $url, array $body): array
        {
            throw new RuntimeException('Upstream unavailable');
        }
    };

    $this->app->instance(GeoApiImporter::class, new GeoApiImporter($http));

    $this->post('/web/geo/import')
        ->assertStatus(502)
        ->assertJson(['message' => 'Upstream unavailable']);
});
