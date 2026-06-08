<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Database\PdoConnection;
use Velm\Schema\SchemaBuilder;
use Velm\Environment;
use Velm\Modules\Base\CurrencyApiImporter;
use Velm\Modules\Base\CurrencyImportService;
use Velm\Modules\GeoData\GeoHttpGateway;
use Velm\Modules\ModuleInstaller;
use Velm\Registry;
use Velm\Web\Http\Controllers\CurrencyImportController;
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

test('currency import endpoint returns forbidden without write access', function (): void {
    $env = app(\Velm\Environment::class);
    $groupId = (int) $env->model('res.groups')->search([['name', '=', 'Public']], limit: 1)->ids()[0];
    $userId = (int) $env->model('res.users')->create([
        'name' => 'No Currency Write',
        'email' => 'nocurrency@velm.test',
        'password' => 'secret',
        'group_ids' => [$groupId],
    ])->ids()[0];

    app()->instance(\Velm\Environment::class, new \Velm\Environment(
        $env->connection,
        $env->registry,
        $userId,
    ));

    $this->actingAs(new GenericUser(['id' => $userId, 'email' => 'nocurrency@velm.test']))
        ->post('/web/currencies/import')
        ->assertForbidden();
});

test('currency import endpoint falls back when upstream request fails', function (): void {
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

    $this->app->instance(
        CurrencyImportService::class,
        new CurrencyImportService(new CurrencyApiImporter($http)),
    );

    $response = $this->post('/web/currencies/import')
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($response->json('imported'))->toBeGreaterThan(0);
});

test('currency import endpoint returns not found when currency model is missing', function (): void {
    $envWithoutCurrency = Registry::using(function (Registry $registry): Environment {
        $connection = PdoConnection::sqliteMemory();
        (new SchemaBuilder($connection))->syncRegistry($registry);

        return new Environment($connection, $registry, uid: 1);
    });

    $response = (new CurrencyImportController)->import(
        $envWithoutCurrency,
        new CurrencyImportService,
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['message'])->toBe('Currency model is not installed.');
});

test('currency import endpoint returns bad gateway when persistence fails', function (): void {
    $env = app(Environment::class);
    $env->connection->execute('DROP TABLE res_currency');

    $this->post('/web/currencies/import')
        ->assertStatus(502);
});
