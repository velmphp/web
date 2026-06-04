<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Velm\Environment;
use Velm\Exception\AccessDeniedException;
use Velm\Modules\Workflow\WorkflowDefinitionError;
use Velm\Modules\Workflow\WorkflowDesigner;
use Velm\Modules\Workflow\WorkflowEngine;
use Velm\Modules\Workflow\WorkflowSchema;
use Velm\Modules\Workflow\WorkflowService;

final class WorkflowController
{
    public function models(Environment $env): JsonResponse
    {
        $env->checkAccess('workflow.definition', 'read');

        return response()->json(WorkflowDesigner::listModels($env));
    }

    public function fields(Request $request, Environment $env): JsonResponse
    {
        $env->checkAccess('workflow.definition', 'read');
        $model = (string) $request->query('model', '');

        return response()->json(WorkflowDesigner::listModelFields($env, $model));
    }

    public function groups(Environment $env): JsonResponse
    {
        $env->checkAccess('workflow.definition', 'read');

        return response()->json(WorkflowDesigner::listGroups($env));
    }

    public function users(Environment $env): JsonResponse
    {
        $env->checkAccess('workflow.definition', 'read');

        return response()->json(WorkflowDesigner::listUsers($env));
    }

    public function storeDefinition(Request $request, Environment $env): JsonResponse
    {
        $env->checkAccess('workflow.definition', 'create');

        return $this->persistDefinition($request, $env, null);
    }

    public function updateDefinition(Request $request, Environment $env, int $workflowId): JsonResponse
    {
        $env->checkAccess('workflow.definition', 'write');

        return $this->persistDefinition($request, $env, $workflowId);
    }

    public function context(Request $request, Environment $env): JsonResponse
    {
        $resModel = (string) $request->query('res_model', '');
        $resId = (int) $request->query('res_id', 0);

        if ($resModel === '' || $resId <= 0) {
            return response()->json(['message' => 'res_model and res_id are required.'], 400);
        }

        $ctx = WorkflowService::formContext($env, $resModel, $resId);

        if ($ctx === null) {
            return response()->json(['has_workflow' => false]);
        }

        return response()->json($ctx);
    }

    public function start(Request $request, Environment $env): JsonResponse
    {
        $resModel = (string) $request->input('res_model', '');
        $resId = (int) $request->input('res_id', 0);

        try {
            $instance = WorkflowService::startForRecord($env, $resModel, $resId);
            $ctx = WorkflowService::formContext($env, $resModel, $resId);

            return response()->json(['instance' => $instance, 'context' => $ctx]);
        } catch (WorkflowDefinitionError|AccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function transitionForm(
        Environment $env,
        int $instanceId,
        string $transitionKey,
    ): View|string {
        $env->checkAccess('workflow.instance', 'write');
        $instance = WorkflowEngine::reloadInstance($env, $instanceId);
        $transitions = WorkflowEngine::availableTransitions($env, $instance);
        $tr = null;

        foreach ($transitions as $candidate) {
            if (($candidate['key'] ?? '') === $transitionKey) {
                $tr = $candidate;
                break;
            }
        }

        if ($tr === null) {
            abort(404, 'Transition not available');
        }

        $recordValues = $this->prefillRecordValues($env, $instance, $tr['form_fields'] ?? []);

        return view('velm-ui::workflow.transition-form', [
            'transition' => $tr,
            'instanceId' => $instanceId,
            'transitionKey' => $transitionKey,
            'values' => $recordValues,
            'errors' => [],
            'formError' => null,
        ]);
    }

    public function transition(
        Request $request,
        Environment $env,
        int $instanceId,
        string $transitionKey,
    ): JsonResponse {
        try {
            $env->checkAccess('workflow.instance', 'write');
            $instance = WorkflowEngine::reloadInstance($env, $instanceId);
            $values = $request->input('values', $request->except(['_token']));

            if (! is_array($values)) {
                $values = [];
            }

            $updated = WorkflowEngine::applyTransition($env, $instance, $transitionKey, $values);
            $ctx = WorkflowService::formContext(
                $env,
                (string) $updated['res_model'],
                (int) $updated['res_id'],
            );

            return response()->json(['instance' => $updated, 'context' => $ctx, 'ok' => true]);
        } catch (WorkflowDefinitionError|AccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(
        Request $request,
        Environment $env,
        int $approvalId,
    ): JsonResponse {
        try {
            $env->checkAccess('workflow.approval', 'write');
            $rows = $env->browse('workflow.approval', [$approvalId])->read();

            if ($rows === []) {
                return response()->json(['message' => 'Approval not found.'], 404);
            }

            $approved = $request->boolean('approved', true);
            $comment = (string) $request->input('comment', '');
            $updated = WorkflowEngine::approve($env, $rows[0], approved: $approved, comment: $comment);
            $ctx = WorkflowService::formContext(
                $env,
                (string) $updated['res_model'],
                (int) $updated['res_id'],
            );

            return response()->json(['instance' => $updated, 'context' => $ctx, 'ok' => true]);
        } catch (WorkflowDefinitionError|AccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function persistDefinition(Request $request, Environment $env, ?int $workflowId): JsonResponse
    {
        $defn = $request->input('definition', []);

        if (! is_array($defn)) {
            return response()->json(['message' => 'definition must be an object'], 400);
        }

        try {
            WorkflowSchema::validate($defn, $env->registry);
        } catch (WorkflowDefinitionError $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        $meta = [
            'name' => (string) $request->input('name', 'Untitled workflow'),
            'description' => (string) $request->input('description', ''),
            'model' => (string) ($request->input('model') ?? $defn['model'] ?? ''),
            'active' => $request->boolean('active', true),
        ];

        if ($workflowId === null) {
            $rec = $env->model('workflow.definition')->create([
                'name' => $meta['name'],
                'description' => $meta['description'],
                'model' => $meta['model'],
                'definition' => json_encode($defn, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                'active' => $meta['active'],
            ]);

            return response()->json(['id' => $rec->ids()[0]]);
        }

        WorkflowService::saveDefinition($env, $workflowId, $meta, $defn);

        return response()->json(['id' => $workflowId]);
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  list<array<string, mixed>>  $formFields
     * @return array<string, mixed>
     */
    private function prefillRecordValues(Environment $env, array $instance, array $formFields): array
    {
        $names = [];

        foreach ($formFields as $ff) {
            if (is_array($ff) && ($ff['source'] ?? 'stage') === 'record' && isset($ff['name'])) {
                $names[] = (string) $ff['name'];
            }
        }

        if ($names === []) {
            return [];
        }

        $rows = $env->browse((string) $instance['res_model'], [(int) $instance['res_id']])->read($names);
        $row = $rows[0] ?? [];

        $values = [];

        foreach ($names as $name) {
            $values[$name] = $row[$name] ?? '';
        }

        return $values;
    }
}
