<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }
});

test('attachment upload rejects empty file when authenticated', function (): void {
    $this->actingAs(new \Illuminate\Auth\GenericUser([
        'id' => 1,
        'remember_token' => null,
    ]));

    $file = UploadedFile::fake()->createWithContent('empty.txt', '', 'text/plain');

    $this->post('/api/attachment/upload', ['file' => $file])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Empty file.');
});

test('attachment upload rejects missing file field when authenticated', function (): void {
    $this->actingAs(new \Illuminate\Auth\GenericUser([
        'id' => 1,
        'remember_token' => null,
    ]));

    $this->postJson('/api/attachment/upload', [])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Multipart field file is required.');
});

test('attachment download returns 404 for missing id', function (): void {
    $this->actingAs(new \Illuminate\Auth\GenericUser([
        'id' => 1,
        'remember_token' => null,
    ]));

    $this->getJson('/api/attachment/999999999/download')
        ->assertNotFound();
});

test('public attachment download works without session auth', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('ir.attachment')->create([
        'name' => 'pub.txt',
        'mimetype' => 'text/plain',
        'type' => 'binary',
        'datas' => base64_encode('public-bytes'),
        'file_size' => 12,
        'public' => true,
    ])->ids()[0];

    $this->get('/api/attachment/'.$id.'/download')
        ->assertOk();
});

test('attachment upload and download round trip when authenticated', function (): void {
    $this->actingAs(new \Illuminate\Auth\GenericUser([
        'id' => 1,
        'remember_token' => null,
    ]));

    $file = UploadedFile::fake()->createWithContent('note.txt', 'hello attachment', 'text/plain');

    $upload = $this->post('/api/attachment/upload', ['file' => $file]);
    $upload->assertCreated();

    $id = (int) $upload->json('id');

    $this->getJson('/api/attachment/'.$id.'/download')
        ->assertOk();
});

test('attachment download handles url attachments via controller', function (): void {
    $env = app(\Velm\Environment::class);
    $id = $env->model('ir.attachment')->create([
        'name' => 'link',
        'mimetype' => 'text/plain',
        'type' => 'url',
        'url' => 'https://example.com/doc',
        'public' => true,
    ])->ids()[0];

    $request = \Illuminate\Http\Request::create('/api/attachment/'.$id.'/download', 'GET');

    expect(fn () => (new \Velm\Web\Http\Controllers\AttachmentController)->download($id, $request, $env))
        ->toThrow(TypeError::class);
});

test('attachment delete returns no content when authenticated', function (): void {
    $this->actingAs(new \Illuminate\Auth\GenericUser([
        'id' => 1,
        'remember_token' => null,
    ]));

    $env = app(\Velm\Environment::class);
    $id = $env->model('ir.attachment')->create([
        'name' => 'del.txt',
        'mimetype' => 'text/plain',
        'type' => 'binary',
        'datas' => base64_encode('bye'),
        'file_size' => 3,
        'public' => false,
    ])->ids()[0];

    $this->deleteJson('/api/attachment/'.$id)
        ->assertNoContent();
});
