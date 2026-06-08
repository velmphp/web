<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Velm\Environment;
use Velm\Exception\AccessDeniedException;

final class AuditExportController
{
    public function exportAuditLogs(Environment $env): StreamedResponse|JsonResponse
    {
        return $this->export(
            $env,
            'ir.audit.log',
            'audit-log.csv',
            ['created_at', 'name', 'model', 'res_id', 'action', 'user_id', 'company_id', 'ip_address', 'user_agent'],
        );
    }

    public function exportLoginLogs(Environment $env): StreamedResponse|JsonResponse
    {
        return $this->export(
            $env,
            'ir.login.log',
            'login-history.csv',
            ['created_at', 'event', 'user_id', 'email', 'ip_address', 'user_agent', 'session_id', 'session_lifetime_minutes'],
        );
    }

    public function exportUserLifecycle(Environment $env): StreamedResponse|JsonResponse
    {
        return $this->export(
            $env,
            'ir.user.lifecycle',
            'user-lifecycle.csv',
            ['created_at', 'event', 'user_id', 'actor_id', 'detail'],
        );
    }

    /**
     * @param  list<string>  $fields
     */
    private function export(
        Environment $env,
        string $model,
        string $filename,
        array $fields,
    ): StreamedResponse|JsonResponse {
        try {
            $env->checkAccess($model, 'read');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        if (! $env->registry->has($model)) {
            return response()->json(['message' => 'Audit module is not installed.'], 404);
        }

        $rows = $env->model($model)->search([], order: 'id desc')->read($fields);

        return response()->streamDownload(function () use ($rows, $fields): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $fields);

            foreach ($rows as $row) {
                $line = [];
                foreach ($fields as $field) {
                    $value = $row[$field] ?? '';
                    $line[] = is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value);
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
