<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Velm\Environment;
use Velm\Views\ViewNotFoundException;
use Velm\Views\ViewRegistry;

final class ViewController
{
    public function __invoke(
        string $module,
        string $name,
        ViewRegistry $views,
        Environment $env,
    ): JsonResponse {
        try {
            return response()->json($views->apiPayload($env, $module, $name));
        } catch (ViewNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }
}
