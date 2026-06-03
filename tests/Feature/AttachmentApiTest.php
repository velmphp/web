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

test('attachment upload and download round-trip', function (): void {
    $file = UploadedFile::fake()->createWithContent('hello.txt', 'hello', 'text/plain');

    $upload = $this->post('/api/attachment/upload', [
        'file' => $file,
        'public' => '1',
    ]);

    $upload->assertCreated();
    $id = (int) $upload->json('id');
    expect($id)->toBeGreaterThan(0);

    $download = $this->get('/api/attachment/'.$id.'/download');

    $download->assertOk();
    expect($download->headers->get('Content-Disposition'))->toContain('hello.txt');
});

test('attachment delete removes row', function (): void {
    $env = app(\Velm\Environment::class);

    $att = $env->model('ir.attachment')->create([
        'name' => 'x.txt',
        'mimetype' => 'text/plain',
        'type' => 'binary',
        'datas' => base64_encode('payload'),
        'file_size' => 7,
    ]);

    $id = $att->ids()[0];

    $this->deleteJson('/api/attachment/'.$id)->assertNoContent();

    expect($env->model('ir.attachment')->search([['id', '=', $id]])->count())->toBe(0);
});
