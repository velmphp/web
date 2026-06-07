<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Velm\Database\PdoConnection;
use Velm\Environment;
use Velm\Registry;
use Velm\Schema\SchemaBuilder;
use Velm\Web\Http\Controllers\AttachmentController;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }
});

test('attachment upload requires authentication when env uid is null', function (): void {
    $env = app(Environment::class);
    $guest = new Environment($env->connection, $env->registry, uid: null);
    $request = Request::create('/api/attachment/upload', 'POST');

    $response = (new AttachmentController)->upload($request, $guest);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['message'])->toBe('Authentication required.');
});

test('attachment upload returns 503 when ir attachment model is unavailable', function (): void {
    $env = Registry::using(function (Registry $registry): Environment {
        $connection = PdoConnection::sqliteMemory();
        (new SchemaBuilder($connection))->syncRegistry($registry);

        return new Environment($connection, $registry, uid: 1);
    });

    $file = UploadedFile::fake()->createWithContent('note.txt', 'payload', 'text/plain');
    $request = Request::create('/api/attachment/upload', 'POST', [], [], ['file' => $file]);

    $response = (new AttachmentController)->upload($request, $env);

    expect($response->getStatusCode())->toBe(503);
});

test('attachment upload returns 403 when create access is denied', function (): void {
    $baseEnv = app(Environment::class);
    $baseEnv->withAclBypass(function () use ($baseEnv): void {
        $baseEnv->model('res.users')->create(['name' => 'No attach', 'email' => 'noattach@test']);
    });
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'noattach@test']])->ids()[0];
    $deniedEnv = new Environment($baseEnv->connection, $baseEnv->registry, uid: $uid);

    $file = UploadedFile::fake()->createWithContent('secret.txt', 'bytes', 'text/plain');
    $request = Request::create('/api/attachment/upload', 'POST', [], [], ['file' => $file]);

    $response = (new AttachmentController)->upload($request, $deniedEnv);

    expect($response->getStatusCode())->toBe(403);
});

test('attachment download requires auth for private attachments', function (): void {
    $env = app(Environment::class);
    $env->withAclBypass(fn () => $env->model('ir.model.access')->create([
        'name' => 'Public attachment read',
        'model' => 'ir.attachment',
        'group_id' => null,
        'perm_read' => true,
    ]));
    $id = $env->model('ir.attachment')->create([
        'name' => 'private.txt',
        'mimetype' => 'text/plain',
        'type' => 'binary',
        'datas' => base64_encode('secret'),
        'file_size' => 6,
        'public' => false,
    ])->ids()[0];

    $guest = new Environment($env->connection, $env->registry, null);
    $request = Request::create('/api/attachment/'.$id.'/download', 'GET');

    $response = (new AttachmentController)->download($id, $request, $guest);

    expect($response->getStatusCode())->toBe(401);
});

test('attachment download redirects url attachments', function (): void {
    $env = app(Environment::class);
    $id = $env->model('ir.attachment')->create([
        'name' => 'link',
        'mimetype' => 'text/plain',
        'type' => 'url',
        'url' => 'https://example.com/doc.pdf',
        'public' => true,
    ])->ids()[0];

    $request = Request::create('/api/attachment/'.$id.'/download', 'GET');

    expect(fn () => (new AttachmentController)->download($id, $request, $env))
        ->toThrow(TypeError::class);
});

test('attachment download returns 404 when url attachment has empty url', function (): void {
    $env = app(Environment::class);
    $id = $env->model('ir.attachment')->create([
        'name' => 'broken-link',
        'mimetype' => 'text/plain',
        'type' => 'url',
        'url' => '',
        'public' => true,
    ])->ids()[0];

    $request = Request::create('/api/attachment/'.$id.'/download', 'GET');
    $response = (new AttachmentController)->download($id, $request, $env);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['message'])->toBe('Attachment URL missing.');
});

test('attachment destroy requires authentication', function (): void {
    $baseEnv = app(Environment::class);
    $id = $baseEnv->model('ir.attachment')->create([
        'name' => 'keep.txt',
        'mimetype' => 'text/plain',
        'type' => 'binary',
        'datas' => base64_encode('data'),
        'file_size' => 4,
        'public' => false,
    ])->ids()[0];

    $guest = new Environment($baseEnv->connection, $baseEnv->registry, null);
    expect((new AttachmentController)->destroy($id, $guest)->getStatusCode())->toBe(401);
});

test('attachment destroy returns 403 on access denied for existing attachment', function (): void {
    $baseEnv = app(Environment::class);
    $id = $baseEnv->model('ir.attachment')->create([
        'name' => 'keep.txt',
        'mimetype' => 'text/plain',
        'type' => 'binary',
        'datas' => base64_encode('data'),
        'file_size' => 4,
        'public' => false,
    ])->ids()[0];

    $baseEnv->withAclBypass(fn () => $baseEnv->model('res.users')->create([
        'name' => 'No unlink attach',
        'email' => 'nounlinkattach@test',
    ]));
    $uid = $baseEnv->model('res.users')->search([['email', '=', 'nounlinkattach@test']])->ids()[0];
    $deniedEnv = new Environment($baseEnv->connection, $baseEnv->registry, $uid);

    expect((new AttachmentController)->destroy($id, $deniedEnv)->getStatusCode())->toBe(403);
});
