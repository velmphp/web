<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Velm\Environment;
use Velm\Modules\GeoData\GeoApiImporter;
use Velm\Exception\AccessDeniedException;

final class GeoImportController
{
    public function import(Environment $env, GeoApiImporter $importer): JsonResponse
    {
        set_time_limit(0);

        try {
            $env->checkAccess('res.country', 'write');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        if (! $env->registry->has('res.continent')
            || ! $env->registry->has('res.country')
            || ! $env->registry->has('res.country.state')
            || ! $env->registry->has('res.city')) {
            return response()->json(['message' => 'Geo data module is not installed.'], 404);
        }

        try {
            $counts = $importer->import($env);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Imported %d countries, %d states and %d cities.',
                $counts['countries'],
                $counts['states'],
                $counts['cities'],
            ),
            'counts' => $counts,
        ]);
    }
}
