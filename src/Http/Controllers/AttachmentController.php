<?php

declare(strict_types=1);

namespace Velm\Web\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Velm\Environment;
use Velm\Exception\AccessDeniedException;
use Velm\Modules\Base\Models\Attachment;
use Velm\Storage\AttachmentStorage;
final class AttachmentController
{
    public function upload(Request $request, Environment $env): JsonResponse
    {
        if ($env->uid === null) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (! $env->registry->has('ir.attachment')) {
            return response()->json(['message' => 'ir.attachment model not loaded.'], 503);
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return response()->json(['message' => 'Multipart field file is required.'], 400);
        }

        $content = $file->getContent();

        if ($content === '') {
            return response()->json(['message' => 'Empty file.'], 400);
        }

        try {
            $env->checkAccess('ir.attachment', 'create');
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $originalName = $file->getClientOriginalName() ?: 'file';
        $backend = AttachmentStorage::backend();
        $storageKey = $backend->save($originalName, $content);
        $mimetype = $file->getMimeType()
            ?: (mime_content_type($file->getRealPath() ?: '') ?: null)
            ?: 'application/octet-stream';

        $values = [
            'name' => $originalName,
            'datas_fname' => $originalName,
            'mimetype' => $mimetype,
            'file_size' => strlen($content),
            'res_model' => $request->input('res_model') ?: null,
            'res_id' => $request->filled('res_id') ? (int) $request->input('res_id') : null,
            'type' => 'binary',
            'storage_key' => $storageKey,
            'datas' => $storageKey === '' ? base64_encode($content) : null,
            'public' => $request->boolean('public'),
        ];

        if ($env->companyId() !== null && $env->registry->hasFieldSet('ir.attachment')) {
            $fields = $env->registry->fieldSet('ir.attachment');

            if (isset($fields['company_id'])) {
                $values['company_id'] = $env->companyId();
            }
        }

        $att = $env->model('ir.attachment')->create($values);
        $rows = $att->read(['id', 'name', 'mimetype', 'file_size']);

        return response()->json([
            'id' => (int) ($rows[0]['id'] ?? 0),
            'name' => (string) ($rows[0]['name'] ?? ''),
            'mimetype' => (string) ($rows[0]['mimetype'] ?? ''),
            'size' => (int) ($rows[0]['file_size'] ?? 0),
        ], 201);
    }

    public function download(int $attId, Request $request, Environment $env): Response|StreamedResponse|JsonResponse
    {
        if (! $env->registry->has('ir.attachment')) {
            return response()->json(['message' => 'ir.attachment model not loaded.'], 503);
        }

        $probe = $env->withAclBypass(
            fn () => $env->model('ir.attachment')->search([['id', '=', $attId]], limit: 1),
        );

        if ($probe->count() === 0) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $rows = $probe->read(['public', 'type', 'url', 'mimetype', 'datas_fname', 'name', 'datas', 'storage_key']);
        $row = $rows[0] ?? [];

        if ((bool) ($row['public'] ?? false)) {
            $att = $probe;
        } else {
            if ($env->uid === null) {
                return response()->json(['message' => 'Authentication required.'], 401);
            }

            try {
                $att = $env->browse('ir.attachment', [$attId]);
                $env->checkAccess('ir.attachment', 'read');
            } catch (AccessDeniedException $exception) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }
        }

        $att->ensureOne();

        if ((string) ($row['type'] ?? '') === 'url') {
            $url = (string) ($row['url'] ?? '');

            if ($url === '') {
                return response()->json(['message' => 'Attachment URL missing.'], 404);
            }

            return redirect()->away($url);
        }

        $content = Attachment::fetchContentFromRow($row);
        $filename = (string) ($row['datas_fname'] ?? $row['name'] ?? "attachment-{$attId}");
        $safeFilename = str_replace('"', '', $filename);
        $cache = (bool) ($row['public'] ?? false) ? 'public' : 'private';

        return response($content, 200, [
            'Content-Type' => (string) ($row['mimetype'] ?? 'application/octet-stream'),
            'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
            'Cache-Control' => "{$cache}, max-age=3600",
        ]);
    }

    public function destroy(int $attId, Environment $env): Response|JsonResponse
    {
        if ($env->uid === null) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (! $env->registry->has('ir.attachment')) {
            return response()->json(['message' => 'ir.attachment model not loaded.'], 503);
        }

        $att = $env->browse('ir.attachment', [$attId]);

        if ($att->count() === 0) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        try {
            $att->unlink();
        } catch (AccessDeniedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->noContent();
    }
}
