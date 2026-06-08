<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Database\PdoConnection;
use Velm\Environment;
use Velm\Modules\ModuleInstaller;
use Velm\Registry;
use Velm\Schema\SchemaBuilder;
use Velm\Web\Http\Controllers\ViewActionsController;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    $roots = [dirname(__DIR__, 3).'/modules/modules'];
    $installer = new ModuleInstaller;
    $installer->installBootstrap($roots, ['base', 'admin']);
    $installer->install('partners', $roots);
    $installer->install('geo_data', $roots);

    $this->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
    ]));
});

test('demo partner seed endpoint loads bundled contacts', function (): void {
    $this->post('/web/demo/partners/seed')
        ->assertOk()
        ->assertJson(['ok' => true]);

    $env = app(\Velm\Environment::class);

    expect($env->model('res.partner')->search([['name', '=', 'Velm SA']])->count())->toBe(1);
});

test('demo partner export returns csv attachment', function (): void {
    $env = app(\Velm\Environment::class);
    $env->model('res.partner')->create(['name' => 'Export Me']);

    $response = $this->get('/web/demo/partners/export');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('partners.csv');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Export Me');
});

test('demo partner duplicate creates copy and returns redirect', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Original Co'])->ids()[0];

    $response = $this->post('/web/demo/partners/'.$id.'/duplicate')
        ->assertOk()
        ->assertJsonStructure(['redirect', 'message']);

    $redirect = (string) $response->json('redirect');
    expect($redirect)->toContain('/velm/views/partners/partner.detail/')
        ->and($env->model('res.partner')->search([['name', '=', 'Original Co (copy)']])->count())->toBe(1);
});

test('demo partner export filters by bulk ids query param', function (): void {
    $env = app(\Velm\Environment::class);
    $included = $env->model('res.partner')->create(['name' => 'Included Partner'])->ids()[0];
    $env->model('res.partner')->create(['name' => 'Excluded Partner']);

    $response = $this->get('/web/demo/partners/export?ids='.$included);

    $response->assertOk();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Included Partner')
        ->and($content)->not->toContain('Excluded Partner');
});

test('demo partner json export downloads record payload', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Json Partner'])->ids()[0];

    $response = $this->get('/web/demo/partners/'.$id.'/export');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('partner-'.$id.'.json')
        ->and($response->getContent())->toContain('Json Partner');
});

test('demo partner endpoints return forbidden without access', function (): void {
    $env = app(\Velm\Environment::class);
    $groupId = (int) $env->model('res.groups')->search([['name', '=', 'Public']], limit: 1)->ids()[0];
    $userId = (int) $env->model('res.users')->create([
        'name' => 'No Partner Access',
        'email' => 'nopartner@velm.test',
        'password' => 'secret',
        'group_ids' => [$groupId],
    ])->ids()[0];

    app()->instance(\Velm\Environment::class, new \Velm\Environment(
        $env->connection,
        $env->registry,
        $userId,
    ));

    $this->actingAs(new GenericUser(['id' => $userId, 'email' => 'nopartner@velm.test']))
        ->post('/web/demo/partners/seed')
        ->assertForbidden();

    $this->actingAs(new GenericUser(['id' => $userId, 'email' => 'nopartner@velm.test']))
        ->get('/web/demo/partners/export')
        ->assertForbidden();
});

test('demo partner endpoints return not found when partners module is missing', function (): void {
    $envWithoutPartners = Registry::using(function (Registry $registry): Environment {
        $connection = PdoConnection::sqliteMemory();
        (new SchemaBuilder($connection))->syncRegistry($registry);

        return new Environment($connection, $registry, uid: 1);
    });

    $response = (new ViewActionsController)->seedPartners($envWithoutPartners);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['message'])->toBe('Partners module is not installed.');
});

test('demo partner export includes country label and accepts bulk ids from request input', function (): void {
    $env = app(\Velm\Environment::class);
    $countryId = (int) $env->model('res.country')->search([['code', '=', 'BE']], limit: 1)->ids()[0];
    $included = (int) $env->model('res.partner')->create([
        'name' => 'Belgian Co',
        'country_id' => $countryId,
    ])->ids()[0];
    $env->model('res.partner')->create(['name' => 'No Country Co']);

    $csvResponse = $this->get('/web/demo/partners/export?ids='.$included)->assertOk();

    ob_start();
    $csvResponse->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Belgian Co')
        ->and($csv)->toContain('Belgium')
        ->and($csv)->not->toContain('No Country Co');

    $request = \Illuminate\Http\Request::create('/web/demo/partners/export', 'POST', [
        'ids' => [$included, 0, -1],
    ]);
    $bulkResponse = (new ViewActionsController)->exportPartners($env, $request);

    ob_start();
    $bulkResponse->sendContent();
    $bulkCsv = ob_get_clean();

    expect($bulkCsv)->toContain('Belgian Co')
        ->and($bulkCsv)->not->toContain('No Country Co');
});

test('demo partner duplicate and json export return not found for missing record', function (): void {
    $this->post('/web/demo/partners/99999/duplicate')
        ->assertNotFound()
        ->assertJson(['message' => 'Partner not found.']);

    $this->get('/web/demo/partners/99999/export')
        ->assertNotFound()
        ->assertJson(['message' => 'Partner not found.']);
});

