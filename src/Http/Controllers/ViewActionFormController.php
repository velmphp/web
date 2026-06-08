<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Velm\Environment;
use Velm\Exception\AccessDeniedException;
use Velm\Ui\Forms\InlineActionFormRenderer;
use Velm\Views\Arch\ViewActionLocator;

final class ViewActionFormController
{
    public function show(
        Request $request,
        Environment $env,
        string $module,
        string $viewName,
        string $slot,
        string $actionKey,
    ): Response|JsonResponse {
        $action = $this->resolvedAction($env, $module, $viewName, $slot, $actionKey);

        if ($action === null) {
            return response()->json(['message' => 'Action not found.'], 404);
        }

        $inlineForm = is_array($action['form'] ?? null) ? $action['form'] : null;

        if ($inlineForm === null || ($inlineForm['sections'] ?? []) === []) {
            return response()->json(['message' => 'Action has no inline form.'], 404);
        }

        $model = (string) ($action['model'] ?? '');
        $recordId = max(0, $request->integer('record'));

        try {
            $this->assertActionAccess($env, $action, $recordId > 0 ? 'write' : 'create');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $values = $recordId > 0
            ? ($env->browse($model, [$recordId])->read()[0] ?? [])
            : [];

        $fields = (new InlineActionFormRenderer)->fields($env, $model, $inlineForm, $values);

        return response()->view('velm-ui::view-actions.inline-form', [
            'title' => (string) ($action['label'] ?? 'Form'),
            'fields' => $fields,
            'submitUrl' => $request->fullUrl(),
            'recordId' => $recordId,
        ]);
    }

    public function submit(
        Request $request,
        Environment $env,
        string $module,
        string $viewName,
        string $slot,
        string $actionKey,
    ): JsonResponse {
        $action = $this->resolvedAction($env, $module, $viewName, $slot, $actionKey);

        if ($action === null) {
            return response()->json(['message' => 'Action not found.'], 404);
        }

        $inlineForm = is_array($action['form'] ?? null) ? $action['form'] : null;

        if ($inlineForm === null) {
            return response()->json(['message' => 'Action has no inline form.'], 404);
        }

        $model = (string) ($action['model'] ?? '');
        $recordId = max(0, $request->integer('record'));

        try {
            $this->assertActionAccess($env, $action, $recordId > 0 ? 'write' : 'create');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        /** @var array<string, mixed> $values */
        $values = $request->json()->all();

        if ($values === []) {
            return response()->json(['message' => 'Request body must be a JSON object.'], 422);
        }

        try {
            if ($recordId > 0) {
                $env->browse($model, [$recordId])->write($values);
                $id = $recordId;
            } else {
                $id = $env->model($model)->create($values)->ids()[0];
            }
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $rows = $env->browse($model, [$id])->read(['display_name']);

        return response()->json([
            'ok' => true,
            'id' => $id,
            'label' => (string) ($rows[0]['display_name'] ?? $id),
            'message' => $recordId > 0 ? 'Record updated.' : 'Record created.',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvedAction(
        Environment $env,
        string $module,
        string $viewName,
        string $slot,
        string $actionKey,
    ): ?array {
        return (new ViewActionLocator)->find($env, $module, $viewName, $slot, $actionKey);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function assertActionAccess(Environment $env, array $action, string $fallbackPerm): void
    {
        $model = (string) ($action['model'] ?? '');
        $perm = (string) ($action['perm'] ?? $fallbackPerm);

        if ($model === '') {
            throw new AccessDeniedException('Action model is missing.');
        }

        $env->checkAccess($model, $perm);
    }
}
