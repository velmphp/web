<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Velm\Modules\Tests\Support\TestUpload;
use Velm\Storage\AttachmentStorage;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    Storage::fake('local');
    AttachmentStorage::resetBackendCache();

    $roots = [dirname(__DIR__, 3).'/modules/modules'];
    $installer = new \Velm\Modules\ModuleInstaller;
    $installer->installBootstrap($roots, ['base', 'admin']);
    $installer->install('file_manager', $roots);

    $this->actingAs(new \Illuminate\Auth\GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
        'remember_token' => null,
    ]));
});

test('file manager bulk download packs binary and url attachments', function (): void {
    $env = app(\Velm\Environment::class);
    $service = new \Velm\Modules\FileManager\FileManagerService($env);
    $service->ensureInstalled();

    $binaryId = (int) $service->storeUpload(TestUpload::file('alpha.txt', 'alpha-content'))['id'];
    $urlId = (int) $env->model('ir.attachment')->create([
        'name' => 'Shortcut',
        'type' => 'url',
        'url' => 'https://example.com/doc',
        'mimetype' => 'text/plain',
    ])->ids()[0];

    $response = $this->postJson('/web/files/bulk/download', ['ids' => [$binaryId, $urlId, 999999]]);

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

test('file manager bulk download accepts comma separated form ids', function (): void {
    $env = app(\Velm\Environment::class);
    $service = new \Velm\Modules\FileManager\FileManagerService($env);
    $service->ensureInstalled();

    $attId = (int) $service->storeUpload(TestUpload::file('comma.txt', 'comma'))['id'];
    $urlId = (int) $env->model('ir.attachment')->create([
        'name' => 'link',
        'type' => 'url',
        'url' => 'https://example.com/comma',
        'mimetype' => 'text/plain',
    ])->ids()[0];

    $this->post('/web/files/bulk/download', ['ids' => $attId.','.$urlId])
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

test('file manager bulk download rejects empty id list', function (): void {
    $this->postJson('/web/files/bulk/download', ['ids' => []])->assertStatus(400);
});

test('file manager bulk download deduplicates archive entry names', function (): void {
    $env = app(\Velm\Environment::class);
    $service = new \Velm\Modules\FileManager\FileManagerService($env);
    $service->ensureInstalled();

    $firstId = (int) $service->storeUpload(TestUpload::file('dup.txt', 'first'))['id'];
    $secondId = (int) $service->storeUpload(TestUpload::file('dup.txt', 'second'))['id'];
    $urlId = (int) $env->model('ir.attachment')->create([
        'name' => 'link',
        'type' => 'url',
        'url' => 'https://example.com/dup',
        'mimetype' => 'text/plain',
    ])->ids()[0];

    $this->postJson('/web/files/bulk/download', ['ids' => [$firstId, $secondId, $urlId]])
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

test('file manager bulk download skips unreadable attachment content', function (): void {
    $env = app(\Velm\Environment::class);
    $service = new \Velm\Modules\FileManager\FileManagerService($env);
    $service->ensureInstalled();

    $createEnv = \Velm\Modules\FileManager\FileManagerCompanyScope::envForCreate($env);

    $attId = (int) $createEnv->model('ir.attachment')->create([
        'name' => 'empty.bin',
        'type' => 'binary',
        'mimetype' => 'application/octet-stream',
        'datas' => null,
        'storage_key' => '',
        'file_size' => 0,
    ])->ids()[0];

    $urlId = (int) $env->model('ir.attachment')->create([
        'name' => 'link',
        'type' => 'url',
        'url' => 'https://example.com/readable',
        'mimetype' => 'text/plain',
    ])->ids()[0];

    $this->postJson('/web/files/bulk/download', ['ids' => [$attId, $urlId]])
        ->assertOk()
        ->assertHeader('X-PV-Skipped', '1');
});
