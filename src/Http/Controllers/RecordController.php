<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Velm\Environment;
use Velm\Web\Api\DomainParser;
use Velm\Web\Api\InvalidDomainException;
use Velm\Web\Api\ModelNotFoundException;
use Velm\Web\Api\RecordNotFoundException;
use Velm\Web\Api\RecordQuery;

final class RecordController
{
    public function index(
        Request $request,
        Environment $env,
        DomainParser $domains,
        RecordQuery $records,
    ): JsonResponse {
        $model = (string) $request->query('model', '');

        if ($model === '') {
            return response()->json(['message' => 'Query parameter model is required.'], 400);
        }

        try {
            $domain = $domains->parse((string) $request->query('domain', '[]'));
        } catch (InvalidDomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }

        $fieldsParam = (string) $request->query('fields', '');
        $fields = $fieldsParam === ''
            ? null
            : array_values(array_filter(array_map(trim(...), explode(',', $fieldsParam))));

        $limit = $request->has('limit') ? max(0, $request->integer('limit')) : 0;
        $offset = max(0, $request->integer('offset'));
        $order = $request->query('order');
        $order = is_string($order) && $order !== '' ? $order : null;

        try {
            return response()->json($records->list(
                $env,
                $model,
                $domain,
                $fields,
                $limit,
                $offset,
                $order,
            ));
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }
    }

    public function store(Request $request, Environment $env, RecordQuery $records): JsonResponse
    {
        $model = (string) $request->query('model', '');

        if ($model === '') {
            return response()->json(['message' => 'Query parameter model is required.'], 400);
        }

        /** @var array<string, mixed> $values */
        $values = $request->json()->all();

        if ($values === []) {
            return response()->json(['message' => 'Request body must be a JSON object.'], 400);
        }

        try {
            return response()->json($records->create($env, $model, $values), 201);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }
    }

    public function update(
        Request $request,
        int $recordId,
        Environment $env,
        RecordQuery $records,
    ): JsonResponse {
        $model = (string) $request->query('model', '');

        if ($model === '') {
            return response()->json(['message' => 'Query parameter model is required.'], 400);
        }

        /** @var array<string, mixed> $values */
        $values = $request->json()->all();

        if ($values === []) {
            return response()->json(['message' => 'Request body must be a JSON object.'], 400);
        }

        try {
            return response()->json($records->write($env, $model, $recordId, $values));
        } catch (ModelNotFoundException|RecordNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }
    }

    public function destroy(
        Request $request,
        int $recordId,
        Environment $env,
        RecordQuery $records,
    ): Response {
        $model = (string) $request->query('model', '');

        if ($model === '') {
            return response(['message' => 'Query parameter model is required.'], 400)
                ->header('Content-Type', 'application/json');
        }

        try {
            $records->delete($env, $model, $recordId);

            return response()->noContent();
        } catch (ModelNotFoundException|RecordNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }
}
