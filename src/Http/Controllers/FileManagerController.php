<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Velm\Environment;
use Velm\Modules\Base\Models\Attachment;
use Velm\Modules\FileManager\FileManagerService;
use ZipArchive;

final class FileManagerController
{
    public function tree(Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        return response()->json($service->folderTree());
    }

    public function picker(Request $request, Environment $env): Response
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $accept = trim((string) $request->query('accept', ''));
        $query = trim((string) $request->query('q', ''));
        $folderId = $request->query('folder_id');
        $folderId = $folderId !== null && $folderId !== '' ? (int) $folderId : null;
        $browse = $service->browse($folderId, $accept, $query);

        return response()->view('velm-ui::file-manager.picker', [
            'pickerConfig' => [
                'accept' => $accept,
                'q' => $query,
                'multi' => $request->boolean('multi'),
                'canUpload' => $env->hasAccess('ir.attachment', 'create'),
                'browse' => $browse,
            ],
            'accept' => $accept,
            'canUpload' => $env->hasAccess('ir.attachment', 'create'),
        ]);
    }

    public function pickerBrowse(Request $request, Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $accept = trim((string) $request->query('accept', ''));
        $query = trim((string) $request->query('q', ''));
        $folderId = $request->query('folder_id');
        $folderId = $folderId !== null && $folderId !== '' ? (int) $folderId : null;

        return response()->json($service->browse($folderId, $accept, $query));
    }

    public function pickerUpload(Request $request, Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        /** @var UploadedFile|null $file */
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(400, 'file is required');
        }

        $folderId = $request->input('folder_id');
        $folderId = $folderId !== null && $folderId !== '' && (int) $folderId > 0 ? (int) $folderId : null;

        $row = $service->storeUpload($file, $request->boolean('public'), $folderId);

        return response()->json($row, 201);
    }

    public function properties(int $attId, Environment $env): Response
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        return response()->view('velm-ui::file-manager.properties-page', $service->propertiesViewData($attId));
    }

    public function propertiesPanel(int $attId, Environment $env): Response
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        return response()->view('velm-ui::file-manager.properties-panel', array_merge(
            $service->propertiesViewData($attId),
            ['panelOnly' => true],
        ));
    }

    public function createFolder(Request $request, Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        return response()->json($service->createFolder($payload), 201);
    }

    public function updateFolder(int $folderId, Request $request, Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        return response()->json($service->updateFolder($folderId, $payload));
    }

    public function deleteFolder(int $folderId, Environment $env): Response
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();
        $service->deleteFolder($folderId);

        return response()->noContent();
    }

    public function move(Request $request, Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $payload = $request->json()->all();
        $ids = array_map(intval(...), $payload['attachment_ids'] ?? []);
        $folderId = $payload['folder_id'] ?? null;
        $folderId = $folderId !== null && $folderId !== '' && (int) $folderId > 0 ? (int) $folderId : null;

        return response()->json(['updated' => $service->moveAttachments($ids, $folderId)]);
    }

    public function copy(Request $request, Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $payload = $request->json()->all();
        $ids = array_map(intval(...), $payload['attachment_ids'] ?? []);
        $folderId = $payload['folder_id'] ?? null;
        $folderId = $folderId !== null && $folderId !== '' && (int) $folderId > 0 ? (int) $folderId : null;

        return response()->json(['copied' => $service->copyAttachments($ids, $folderId)]);
    }

    public function bulkDownload(Request $request, Environment $env): StreamedResponse|Response
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();
        $env->checkAccess('ir.attachment', 'read');

        $ids = $this->bulkIdsFromRequest($request);

        if ($ids === []) {
            abort(400, 'ids is required');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'velm-files-');
        $zip = new ZipArchive;

        if ($zipPath === false || $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create archive');
        }

        $skipped = 0;
        $seen = [];

        foreach ($ids as $id) {
            $att = $env->browse('ir.attachment', [$id]);

            if ($att->count() === 0) {
                continue;
            }

            $row = $att->read(['id', 'name', 'datas_fname', 'type', 'url'])[0] ?? [];
            $baseName = trim((string) ($row['datas_fname'] ?? $row['name'] ?? 'attachment-'.$id)) ?: 'attachment-'.$id;
            $count = $seen[$baseName] ?? 0;
            $seen[$baseName] = $count + 1;
            $arcName = $count === 0 ? $baseName : $count.'-'.$baseName;

            if (($row['type'] ?? '') === 'url') {
                $body = '[InternetShortcut]'."\n".'URL='.((string) ($row['url'] ?? ''))."\n";
                $zip->addFromString($arcName.'.url', $body);

                continue;
            }

            try {
                $data = Attachment::fetchContentFromRow($row);
            } catch (\Throwable) {
                $skipped++;

                continue;
            }

            if ($data === '') {
                $skipped++;

                continue;
            }

            $zip->addFromString($arcName, $data);
        }

        $zip->close();

        $headers = [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="files-'.count($ids).'.zip"',
        ];

        if ($skipped > 0) {
            $headers['X-PV-Skipped'] = (string) $skipped;
        }

        return response()->download($zipPath, 'files-'.count($ids).'.zip', $headers)->deleteFileAfterSend(true);
    }

    public function bulkDelete(Request $request, Environment $env): Response
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $ids = $this->bulkIdsFromRequest($request);
        $service->deleteAttachments($ids);

        return response()->noContent();
    }

    public function bulkPublic(Request $request, Environment $env): JsonResponse
    {
        $this->guardAuth();
        $service = new FileManagerService($env);
        $service->ensureInstalled();

        $payload = $request->json()->all();
        $ids = array_map(intval(...), $payload['ids'] ?? []);
        $public = (bool) ($payload['public'] ?? true);

        return response()->json(['updated' => $service->setPublic($ids, $public)]);
    }

    /**
     * @return list<int>
     */
    private function bulkIdsFromRequest(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload !== []) {
            $raw = $payload['ids'] ?? [];

            return array_values(array_filter(array_map(intval(...), is_array($raw) ? $raw : [])));
        }

        $raw = (string) $request->input('ids', '');

        return array_values(array_filter(array_map(
            intval(...),
            array_filter(explode(',', $raw), static fn (string $part): bool => trim($part) !== '' && ctype_digit(trim($part))),
        )));
    }

    private function guardAuth(): void
    {
        if (Auth::id() === null) {
            abort(401);
        }
    }
}
