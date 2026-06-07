<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Velm\Modules\ModuleInstaller;
use Velm\Modules\Tests\Support\TestUpload;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    $roots = [dirname(__DIR__, 3).'/modules/modules'];
    $installer = new ModuleInstaller;
    $installer->installBootstrap($roots, ['base', 'admin']);
    $installer->install('file_manager', $roots);
});

function fileManagerActAsAdmin(): void
{
    test()->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
        'remember_token' => null,
    ]));
}

test('file manager tree endpoint requires authentication', function (): void {
    $this->getJson('/web/files/tree')->assertUnauthorized();
});

test('picker browse upload and folder crud via api', function (): void {
    fileManagerActAsAdmin();
    $create = $this->postJson('/web/files/folders', ['name' => 'API Folder']);

    $create->assertCreated()
        ->assertJsonPath('name', 'API Folder');

    $folderId = (int) $create->json('id');

    $this->getJson('/web/files/picker/browse?folder_id='.$folderId)
        ->assertOk()
        ->assertJsonPath('folder_id', $folderId);

    $file = TestUpload::file('api.txt', 'api-content');

    $upload = $this->post('/web/files/picker/upload', [
        'file' => $file,
        'folder_id' => $folderId,
        'public' => '1',
    ]);

    $upload->assertCreated();
    $attId = (int) $upload->json('id');

    $this->patchJson('/web/files/folders/'.$folderId, ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed');

    $this->postJson('/web/files/move', [
        'attachment_ids' => [$attId],
        'folder_id' => null,
    ])->assertOk()->assertJsonPath('updated', 1);

    $this->postJson('/web/files/copy', [
        'attachment_ids' => [$attId],
        'folder_id' => $folderId,
    ])->assertOk()->assertJsonPath('copied', 1);

    $this->postJson('/web/files/bulk/public', [
        'ids' => [$attId],
        'public' => true,
    ])->assertOk();

    $this->get('/web/files/'.$attId.'/properties_panel')->assertOk();

    $this->postJson('/web/files/bulk/delete', ['ids' => [$attId]])->assertNoContent();
});

test('picker upload requires file field', function (): void {
    fileManagerActAsAdmin();

    $this->postJson('/web/files/picker/upload', [])
        ->assertStatus(400);
});

test('file manager tree and picker page render for authenticated user', function (): void {
    fileManagerActAsAdmin();

    $this->getJson('/web/files/tree')
        ->assertOk()
        ->assertJsonStructure(['folders', 'unfiled_count']);

    $this->get('/web/files/picker')
        ->assertOk()
        ->assertSee('picker', false);
});

test('picker page accepts multi and accept query parameters', function (): void {
    fileManagerActAsAdmin();

    $this->get('/web/files/picker?multi=1&accept=image/png&q=logo')
        ->assertOk()
        ->assertSee('picker', false);
});

test('create folder accepts form encoded payload', function (): void {
    fileManagerActAsAdmin();

    $this->post('/web/files/folders', ['name' => 'Form Folder', 'parent_id' => ''])
        ->assertCreated()
        ->assertJsonPath('name', 'Form Folder');
});

test('folder delete via api', function (): void {
    fileManagerActAsAdmin();

    $folder = $this->postJson('/web/files/folders', ['name' => 'DeleteMe'])->assertCreated();
    $folderId = (int) $folder->json('id');

    $this->deleteJson('/web/files/folders/'.$folderId)->assertNoContent();
});

test('bulk download requires ids', function (): void {
    fileManagerActAsAdmin();

    $this->postJson('/web/files/bulk/download', [])->assertStatus(400);
});

test('picker browse supports search query and accept filter', function (): void {
    fileManagerActAsAdmin();

    $folder = $this->postJson('/web/files/folders', ['name' => 'SearchFolder'])->json('id');
    $pdf = TestUpload::file('findme.pdf', '%PDF', 'application/pdf');
    $this->post('/web/files/picker/upload', [
        'file' => $pdf,
        'folder_id' => $folder,
    ]);

    $this->getJson('/web/files/picker/browse?folder_id='.$folder.'&q=findme&accept=application/pdf')
        ->assertOk()
        ->assertJsonPath('rows.0.name', 'findme.pdf');
});

test('file properties page renders for attachment', function (): void {
    fileManagerActAsAdmin();

    $file = TestUpload::file('props.txt', 'props');
    $attId = (int) $this->post('/web/files/picker/upload', ['file' => $file])->json('id');

    $this->get('/web/files/'.$attId.'/properties')
        ->assertOk();
});
