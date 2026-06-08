<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
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

test('demo partner json export downloads record payload', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('res.partner')->create(['name' => 'Json Partner'])->ids()[0];

    $response = $this->get('/web/demo/partners/'.$id.'/export');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('partner-'.$id.'.json')
        ->and($response->getContent())->toContain('Json Partner');
});

