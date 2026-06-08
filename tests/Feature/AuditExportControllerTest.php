<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Modules\ModuleInstaller;
use Velm\Modules\SystemAudit\AuditLoginLogger;
use Velm\Web\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! extension_loaded('pdo_sqlite')) {
        skip('The pdo_sqlite extension is required.');
    }

    $roots = [dirname(__DIR__, 3).'/modules/modules'];
    $installer = new ModuleInstaller;
    $installer->installBootstrap($roots, ['base', 'admin']);
    $installer->install('system_audit', $roots);
    $this->env = $installer->environment($roots);

    AuditLoginLogger::log($this->env, 'login_success', 1, 'admin@velm.test');
});

test('audit log export returns csv for authenticated admin', function (): void {
    $response = $this->actingAs(new GenericUser(['id' => 1, 'email' => 'admin@test']))
        ->get('/web/audit/logs/export');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('audit-log.csv');
    expect($response->streamedContent())->toContain('action');
});

test('login history export returns csv', function (): void {
    $response = $this->actingAs(new GenericUser(['id' => 1, 'email' => 'admin@test']))
        ->get('/web/audit/logins/export');

    $response->assertOk();
    expect($response->streamedContent())->toContain('login_success');
});
