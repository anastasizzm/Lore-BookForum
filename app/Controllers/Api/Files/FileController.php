<?php
declare(strict_types=1);

namespace App\Controllers\Api\Files;

use App\Http\HttpContext;
use App\Http\HttpException;
use App\Http\Response;
use App\Extensions\UploadedFiles;
use App\Extensions\EnumExtensions;
use App\Exceptions\NotFoundException;

use App\Models\Files\File;
use App\Models\Errors\Error;
use App\Models\Errors\ValidationError;
use App\Models\Uuid;
use App\Models\Enums\FileCategory;

use App\Extensions\Parsers\RouteParamParser;

use App\Repositories\Storage\FilesRepository;
use App\Storage\FileStorage;

use App\Lib\Settings\StorageSettings;
use App\Lib\I18n\Translator;
use App\Constants;
use App\ErrorCodes;

final class FileController
{
    public function __construct(
        private readonly FileService $service,
        private readonly StorageSettings $settings,
        private readonly Translator $translator
    ) {}

    public function upload(HttpContext $context): Response
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $body = $context->request->body();
        $category = EnumExtensions::tryResolve(FileCategory::class, $body['category']);
        if ($category === null)
            return $this->jsonError(new ValidationError(['category' => [$this->translator->t('errors.common.required')]]), 422);

        $upload = UploadedFiles::one($ctx->request->files, 'file');
        $file = $this->service->storePending($upload, $category, $userId);

        return $this->jsonCreatedId($file->id);
    }

    public function download(HttpContext $ctx): Response
    {
        $file = $this->resolveFile($ctx);
        return $this->fileResponse($file, disposition: 'attachment');
    }

    public function preview(HttpContext $ctx): Response
    {
        $file = $this->resolveFile($ctx);
        return $this->fileResponse($file, disposition: 'inline');
    }

    // ---------- internals ----------

    private function resolveFile(HttpContext $ctx, string $fileId): File
    {
        $fileId = RouteParamParser::uuid(['u' => $fileId], 'u');
        $file = $this->files->findById($fileId);

        if ($file === null) {
            throw new NotFoundException($this->translator->t("errors.common.not_found"), ErrorCodes::NOT_FOUND);
        }

        $this->authorize($ctx, $file);
        return $file;
    }

    private function fileResponse(File $file, string $disposition): Response
    {
        $headers = [
            'Content-Type'        => $file->mimeType,
            'Content-Disposition' => $this->buildContentDisposition($disposition, $file->originalName),
            'Cache-Control'       => $this->buildCacheControl($file),
        ];

        if ($this->settings->useXAccel) {
            return new Response('', 200, $headers + [
                'X-Accel-Redirect' => $this->settings->internalUrlPrefix . '/' . $file->path,
            ]);
        }

        return $this->phpFallback($file, $headers);
    }

    private function authorize(HttpContext $ctx, File $file): void
    {
        if ($this->settings->fileIsPublic($file->category)) {
            return;
        }

        if ($ctx->attribute(Constants::USER_ID_ATTR) === null) {
            throw new UnauthorizedException($this->translator->t("errors.common.unauthorized"));
        }
    }

    private function buildContentDisposition(string $disposition, string $filename): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename);
        $ascii = str_replace(['"', '\\', "\r", "\n"], '', $ascii);

        if ($ascii === '') {
            $ascii = 'download';
        }

        $encoded = rawurlencode($filename);

        return "$disposition; filename=\"$ascii\"; filename*=UTF-8''$encoded";
    }

    private function buildCacheControl(File $file): string
    {
        if (in_array($file->category, self::PUBLIC_CATEGORIES, true)) {
            return 'public, max-age=31536000, immutable';
        }

        return 'private, max-age=3600';
    }

    private function phpFallback(File $file, array $headers): Response
    {
        $absolute = $this->storage->absolutePath($file->path);

        if (!is_file($absolute)) {
            throw new HttpException('File missing on disk', 404);
        }

        $size = filesize($absolute);

        return Response::file(
            path:    $absolute,
            status:  200,
            headers: $headers + [
                'Accept-Ranges'  => 'bytes',
                'Content-Length' => (string) $size,
            ],
        );
    }
}