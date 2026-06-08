<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Database\PdoConnection;
use Velm\Environment;
use Velm\Modules\ModuleInstaller;
use Velm\Modules\SystemAudit\AuditLoginLogger;
use Velm\Registry;
use Velm\Schema\SchemaBuilder;
use Velm\Web\Http\Controllers\AuditExportController;
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

test('user lifecycle export returns csv', function (): void {
    $this->env->withAclBypass(fn () => $this->env->model('ir.user.lifecycle')->create([
        'user_id' => 1,
        'event' => 'created',
        'detail' => json_encode(['name' => 'Admin'], JSON_THROW_ON_ERROR),
    ]));

    $response = $this->actingAs(new GenericUser(['id' => 1, 'email' => 'admin@test']))
        ->get('/web/audit/lifecycle/export');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('user-lifecycle.csv');
    expect($response->streamedContent())->toContain('created');
});

test('audit export returns 403 when read access is denied', function (): void {
    $this->env->withAclBypass(function (): void {
        $this->env->model('res.users')->create([
            'name' => 'No Audit',
            'email' => 'noaudit@test',
        ]);
    });

    $uid = $this->env->model('res.users')->search([['email', '=', 'noaudit@test']])->ids()[0];
    $deniedEnv = new Environment($this->env->connection, $this->env->registry, uid: $uid);
    $this->app->instance(Environment::class, $deniedEnv);

    $response = $this->actingAs(new GenericUser(['id' => $uid, 'email' => 'noaudit@test']))
        ->get('/web/audit/logs/export');

    expect($response->getStatusCode())->toBe(403)
        ->and($response->json('message'))->not->toBeEmpty();
});

test('audit export returns 404 when audit module is not installed', function (): void {
    $envWithoutAudit = Registry::using(function (Registry $registry): Environment {
        $connection = PdoConnection::sqliteMemory();
        (new SchemaBuilder($connection))->syncRegistry($registry);

        return new Environment($connection, $registry, uid: 1);
    });

    $response = (new AuditExportController)->exportAuditLogs($envWithoutAudit);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['message'])->toBe('Audit module is not installed.');
});
