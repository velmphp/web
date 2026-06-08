<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Velm\Environment;
use Velm\Exception\AccessDeniedException;
use Velm\Modules\Partners\Seeders\PartnerDemoSeeder;

/**
 * Demo endpoints wired from arch page_actions / header_actions in bundled modules.
 */
final class ViewActionsController
{
    public function seedPartners(Environment $env): JsonResponse
    {
        try {
            $env->checkAccess('res.partner', 'create');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        if (! $env->registry->has('res.partner')) {
            return response()->json(['message' => 'Partners module is not installed.'], 404);
        }

        PartnerDemoSeeder::run($env);

        return response()->json([
            'ok' => true,
            'message' => 'Demo partners loaded.',
        ]);
    }

    public function exportPartners(Environment $env): StreamedResponse|JsonResponse
    {
        try {
            $env->checkAccess('res.partner', 'read');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        if (! $env->registry->has('res.partner')) {
            return response()->json(['message' => 'Partners module is not installed.'], 404);
        }

        $rows = $env->model('res.partner')->search([], order: 'name asc')->read([
            'name',
            'is_company',
            'active',
            'country_id',
        ]);

        return response()->streamDownload(function () use ($rows, $env): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['name', 'is_company', 'active', 'country']);

            foreach ($rows as $row) {
                $countryLabel = '';

                if (($row['country_id'] ?? false) !== false && $env->registry->has('res.country')) {
                    $countryRows = $env->browse('res.country', [(int) $row['country_id']])->read(['name']);
                    $countryLabel = (string) ($countryRows[0]['name'] ?? '');
                }

                fputcsv($handle, [
                    (string) ($row['name'] ?? ''),
                    ($row['is_company'] ?? false) ? 'yes' : 'no',
                    ($row['active'] ?? false) ? 'yes' : 'no',
                    $countryLabel,
                ]);
            }

            fclose($handle);
        }, 'partners.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPartner(Environment $env, int $id): Response|JsonResponse
    {
        try {
            $env->checkAccess('res.partner', 'read');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $rows = $env->browse('res.partner', [$id])->read();

        if ($rows === []) {
            return response()->json(['message' => 'Partner not found.'], 404);
        }

        $payload = json_encode($rows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'partner-'.$id.'.json';

        return response($payload === false ? '{}' : $payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function duplicatePartner(Environment $env, int $id, Request $request): JsonResponse
    {
        try {
            $env->checkAccess('res.partner', 'create');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $rows = $env->browse('res.partner', [$id])->read();

        if ($rows === []) {
            return response()->json(['message' => 'Partner not found.'], 404);
        }

        $values = $rows[0];
        unset($values['id'], $values['display_name'], $values['created_at'], $values['updated_at']);
        $values['name'] = trim((string) ($values['name'] ?? 'Partner')).' (copy)';

        $newId = $env->model('res.partner')->create($values)->ids()[0];

        return response()->json([
            'ok' => true,
            'message' => 'Partner duplicated.',
            'redirect' => '/velm/views/partners/partner.detail/'.$newId,
        ]);
    }

}
