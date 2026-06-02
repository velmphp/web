<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Velm\Environment;
use Velm\Web\Api\DomainParser;
use Velm\Web\Api\InvalidDomainException;
use Velm\Web\Api\ModelNotFoundException;
use Velm\Web\Api\RecordQuery;

final class RecordController
{
    public function __invoke(
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
}
