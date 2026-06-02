<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Velm\Environment;
use Velm\Web\Api\ModelNotFoundException;
use Velm\Web\Api\Many2oneSearch;

final class Many2oneController
{
    public function search(
        Request $request,
        Environment $env,
        Many2oneSearch $search,
    ): JsonResponse {
        $model = (string) $request->query('model', '');

        if ($model === '') {
            return response()->json(['message' => 'Query parameter model is required.'], 400);
        }

        $query = trim((string) $request->query('q', ''));
        $limit = min(100, max(1, $request->integer('limit', 10)));

        try {
            return response()->json($search->search($env, $model, $query, $limit));
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }
}
