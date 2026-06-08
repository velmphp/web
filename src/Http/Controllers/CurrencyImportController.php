<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Velm\Environment;
use Velm\Exception\AccessDeniedException;
use Velm\Modules\Base\CurrencyImportService;

final class CurrencyImportController
{
    public function import(Environment $env, CurrencyImportService $service): JsonResponse
    {
        set_time_limit(0);

        try {
            $env->checkAccess('res.currency', 'write');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        if (! $env->registry->has('res.currency')) {
            return response()->json(['message' => 'Currency model is not installed.'], 404);
        }

        try {
            $result = $service->import($env);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'ok' => true,
            'message' => sprintf('Imported %d currencies.', $result['imported']),
            'imported' => $result['imported'],
        ]);
    }
}
