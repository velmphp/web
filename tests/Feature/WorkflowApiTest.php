<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Velm\Modules\ModuleInstaller;
use Velm\Modules\Workflow\WorkflowEngine;
use Velm\Modules\Workflow\WorkflowService;
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
    $installer->install('workflow', $roots);

    $this->actingAs(new GenericUser([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@test',
    ]));
});

test('workflow context requires res_model and res_id', function (): void {
    $this->getJson('/web/workflow/context')
        ->assertStatus(400)
        ->assertJsonPath('message', 'res_model and res_id are required.');
});

test('workflow context returns has_workflow false when no definition applies', function (): void {
    $this->getJson('/web/workflow/context?res_model=res.country&res_id=1')
        ->assertOk()
        ->assertJsonPath('has_workflow', false);
});

test('workflow context returns started instance after auto start', function (): void {
    $env = app(\Velm\Environment::class);
    $partner = $env->model('res.partner')->create(['name' => 'Workflow Partner']);
    $partnerId = $partner->ids()[0];

    $this->getJson('/web/workflow/context?res_model=res.partner&res_id='.$partnerId)
        ->assertOk()
        ->assertJsonPath('has_workflow', true)
        ->assertJsonPath('started', true)
        ->assertJsonPath('state', 'draft');
});

test('workflow builder api lists models fields groups and users', function (): void {
    $this->getJson('/web/workflow/api/models')
        ->assertOk()
        ->assertJsonStructure([['value', 'label']]);

    $this->getJson('/web/workflow/api/fields?model=res.partner')
        ->assertOk();

    $this->getJson('/web/workflow/api/groups')
        ->assertOk();

    $this->getJson('/web/workflow/api/users')
        ->assertOk();
});

test('workflow store definition validates and persists', function (): void {
    $definition = [
        'version' => 1,
        'model' => 'res.partner',
        'states' => [
            ['key' => 'draft', 'label' => 'Draft', 'initial' => true],
            ['key' => 'done', 'label' => 'Done', 'final' => true],
        ],
        'transitions' => [
            ['key' => 'finish', 'label' => 'Finish', 'from' => ['draft'], 'to' => 'done', 'kind' => 'user'],
        ],
    ];

    $create = $this->postJson('/web/workflow/api/definitions', [
        'name' => 'API Test Flow',
        'model' => 'res.partner',
        'definition' => $definition,
    ]);

    $create->assertOk()->assertJsonStructure(['id']);
    $workflowId = (int) $create->json('id');

    $this->putJson('/web/workflow/api/definitions/'.$workflowId, [
        'name' => 'API Test Flow Updated',
        'model' => 'res.partner',
        'definition' => $definition,
    ])->assertOk()->assertJsonPath('id', $workflowId);
});

test('workflow store definition rejects invalid payload', function (): void {
    $this->postJson('/web/workflow/api/definitions', [
        'name' => 'Bad',
        'definition' => 'not-an-array',
    ])->assertStatus(400);

    $this->postJson('/web/workflow/api/definitions', [
        'name' => 'Bad',
        'definition' => ['model' => 'missing.states'],
    ])->assertStatus(400);
});

test('workflow start and transition endpoints drive partner flow', function (): void {
    $env = app(\Velm\Environment::class);
    $partner = $env->model('res.partner')->create(['name' => 'Transition Partner']);
    $partnerId = $partner->ids()[0];

    $this->postJson('/web/workflow/start', [
        'res_model' => 'res.partner',
        'res_id' => $partnerId,
    ])->assertOk()->assertJsonStructure(['instance', 'context']);

    $inst = WorkflowEngine::instanceForRecord($env, 'res.partner', $partnerId);
    expect($inst)->not->toBeNull();

    $instanceId = (int) $inst['id'];

    $this->get('/web/workflow/instances/'.$instanceId.'/transition/submit/form')
        ->assertOk()
        ->assertSee('Submission', false);

    $this->postJson('/web/workflow/instances/'.$instanceId.'/transition/submit', [
        'values' => ['submission_note' => 'Looks good'],
    ])->assertOk()->assertJsonPath('ok', true);
});

test('workflow approve endpoint returns 404 for missing approval', function (): void {
    $this->postJson('/web/workflow/approvals/999999/act', [
        'approved' => true,
        'comment' => 'n/a',
    ])->assertStatus(404);
});

test('workflow transition returns 422 for unknown transition key', function (): void {
    $env = app(\Velm\Environment::class);
    $partnerId = $env->model('res.partner')->create(['name' => 'Bad Transition'])->ids()[0];

    WorkflowService::startForRecord($env, 'res.partner', $partnerId);
    $inst = WorkflowEngine::instanceForRecord($env, 'res.partner', $partnerId);

    $this->postJson('/web/workflow/instances/'.$inst['id'].'/transition/not_a_real_key', [
        'values' => [],
    ])->assertStatus(422);
});

test('workflow transition form returns 404 when transition unavailable', function (): void {
    $env = app(\Velm\Environment::class);
    $partnerId = $env->model('res.partner')->create(['name' => 'No Form'])->ids()[0];

    WorkflowService::startForRecord($env, 'res.partner', $partnerId);
    $inst = WorkflowEngine::instanceForRecord($env, 'res.partner', $partnerId);

    $this->get('/web/workflow/instances/'.$inst['id'].'/transition/finish/form')
        ->assertStatus(404);
});

test('workflow start returns 422 when model has no workflow', function (): void {
    $env = app(\Velm\Environment::class);
    $countryId = $env->model('res.country')->search(limit: 1)->ids()[0];

    $this->postJson('/web/workflow/start', [
        'res_model' => 'res.country',
        'res_id' => $countryId,
    ])->assertStatus(422);
});

test('workflow approve returns 422 when approval is already completed', function (): void {
    $env = app(\Velm\Environment::class);
    $partnerId = $env->model('res.partner')->create(['name' => 'Double Approve'])->ids()[0];

    WorkflowService::startForRecord($env, 'res.partner', $partnerId);
    $inst = WorkflowEngine::instanceForRecord($env, 'res.partner', $partnerId);

    $this->postJson('/web/workflow/instances/'.$inst['id'].'/transition/submit', [
        'values' => ['submission_note' => 'Once only'],
    ])->assertOk();

    $approvalId = (int) $env->model('workflow.approval')->search([
        ['instance_id', '=', (int) $inst['id']],
        ['status', '=', 'pending'],
    ])->ids()[0];

    $this->postJson('/web/workflow/approvals/'.$approvalId.'/act', [
        'approved' => true,
        'comment' => 'First',
    ])->assertOk();

    $this->postJson('/web/workflow/approvals/'.$approvalId.'/act', [
        'approved' => true,
        'comment' => 'Second',
    ])->assertStatus(422);
});

test('workflow approve endpoint completes partner approval', function (): void {
    $env = app(\Velm\Environment::class);
    $partnerId = $env->model('res.partner')->create(['name' => 'API Approve Partner'])->ids()[0];

    WorkflowService::startForRecord($env, 'res.partner', $partnerId);
    $inst = WorkflowEngine::instanceForRecord($env, 'res.partner', $partnerId);

    $this->postJson('/web/workflow/instances/'.$inst['id'].'/transition/submit', [
        'values' => ['submission_note' => 'API approval path'],
    ])->assertOk();

    $approvalId = (int) $env->model('workflow.approval')->search([
        ['instance_id', '=', (int) $inst['id']],
        ['status', '=', 'pending'],
    ])->ids()[0];

    $this->postJson('/web/workflow/approvals/'.$approvalId.'/act', [
        'approved' => true,
        'comment' => 'LGTM via API',
    ])->assertOk()->assertJsonPath('ok', true);
});
