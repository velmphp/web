<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Velm\Environment;
use Velm\Exception\AccessDeniedException;
use Velm\Modules\Mail\MailThreadService;

final class MailThreadController
{
    public function thread(Request $request, Environment $env): JsonResponse
    {
        $resModel = (string) $request->query('res_model', '');
        $resId = (int) $request->query('res_id', 0);

        if ($resModel === '' || $resId <= 0) {
            return response()->json(['message' => 'res_model and res_id are required.'], 400);
        }

        $ctx = MailThreadService::threadContext($env, $resModel, $resId);

        if ($ctx === null) {
            return response()->json(['has_thread' => false]);
        }

        return response()->json($ctx);
    }

    public function postMessage(Request $request, Environment $env): JsonResponse
    {
        $resModel = (string) $request->input('res_model', '');
        $resId = (int) $request->input('res_id', 0);
        $body = (string) $request->input('body', '');

        if ($resModel === '' || $resId <= 0) {
            return response()->json(['message' => 'res_model and res_id are required.'], 400);
        }

        try {
            $message = MailThreadService::postMessage($env, $resModel, $resId, $body);

            return response()->json([
                'ok' => true,
                'message' => $message,
                'thread' => MailThreadService::threadContext($env, $resModel, $resId),
            ]);
        } catch (AccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function follow(Request $request, Environment $env): JsonResponse
    {
        $resModel = (string) $request->input('res_model', '');
        $resId = (int) $request->input('res_id', 0);
        $follow = $request->boolean('follow', true);

        if ($resModel === '' || $resId <= 0) {
            return response()->json(['message' => 'res_model and res_id are required.'], 400);
        }

        try {
            MailThreadService::setFollowing($env, $resModel, $resId, $follow);

            return response()->json([
                'ok' => true,
                'thread' => MailThreadService::threadContext($env, $resModel, $resId),
            ]);
        } catch (AccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
