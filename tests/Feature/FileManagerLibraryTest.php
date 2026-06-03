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
    $installer->install('file_manager', $roots);

    $this->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
    ]));
});

test('file library page renders PyVelm shell hooks', function (): void {
    $this->get('/web/files/library')
        ->assertOk()
        ->assertSee('pvFileLibrary', false)
        ->assertSee('data-pv-library-cfg', false)
        ->assertSee('pv-file-library', false)
        ->assertSee('Upload', false)
        ->assertSee('Library', false)
        ->assertSee('All files', false)
        ->assertSee('Folders', false)
        ->assertDontSee('pv-sidebar-nav--secondary', false)
        ->assertDontSee('{#', false);
});

test('files app rail opens library by default', function (): void {
    $env = app(\Velm\Environment::class);
    $tree = app(\Velm\Views\Menu\MenuTreeBuilder::class)->build($env, '/web/files/library');
    [$root] = \Velm\Views\Menu\MenuTreeBuilder::activeRoot($tree, '/web/files/library');

    expect($root)->not->toBeNull()
        ->and($root['label'] ?? null)->toBe('Files')
        ->and(\Velm\Views\Menu\MenuTreeBuilder::entryHref($root))->toBe('/web/files/library');
});

test('all files list is readonly', function (): void {
    $env = app(\Velm\Environment::class);
    $arch = app(\Velm\Views\ViewRegistry::class)->arch($env, 'file_manager', 'file.list');

    expect($arch['readonly'] ?? false)->toBeTrue()
        ->and($arch['form_view'] ?? null)->toBeNull()
        ->and($arch['detail_view'] ?? null)->toBe('file.detail');
});

test('all files list page hides new button', function (): void {
    $this->get('/velm/views/file_manager/file.list')
        ->assertOk()
        ->assertDontSee('>'.__('New').'<', false);
});

test('files submenu lists library before all files', function (): void {
    $env = app(\Velm\Environment::class);
    $tree = app(\Velm\Views\Menu\MenuTreeBuilder::class)->build($env, '/web/files/library');
    [$root] = \Velm\Views\Menu\MenuTreeBuilder::activeRoot($tree, '/web/files/library');
    $labels = array_map(
        static fn (array $item): string => (string) ($item['label'] ?? ''),
        $root['children'] ?? [],
    );

    expect($labels)->toBe(['Library', 'All files', 'Folders']);
});

test('file library tree endpoint returns folders payload', function (): void {
    $this->getJson('/web/files/tree')
        ->assertOk()
        ->assertJsonStructure(['folders', 'unfiled_count']);
});
