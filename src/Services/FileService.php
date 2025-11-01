<?php

namespace EduVl\FileKit\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use EduVl\FileKit\Contracts\UploadResult;
use EduVl\FileKit\Exceptions\InvalidMimeException;
use EduVl\FileKit\Exceptions\UnsafePathException;
use EduVl\FileKit\Support\MimeMap;
use finfo;

class FileService
{
    protected Disk $disk;
    protected string $baseDir;
    protected array $allowedMimes;
    protected int $maxSize;

    public function __construct(Disk $disk, string $baseDir, array $allowedMimes, int $maxSize)
    {
        $this->disk =           $disk;
        $this->baseDir =        $baseDir;
        $this->allowedMimes =   $allowedMimes;
        $this->maxSize =        $maxSize;
    }

    /** @return UploadResult */
    public function upload(string|UploadedFile $file, ?string $filename = null, ?string $directory = null): UploadResult
    {
        $dir = $this->safeJoin($this->baseDir, $directory ?? '');
        [$bytes, $mime] = $this->readInput($file);
        $this->assertAllowed($mime);
        $ext = $this->mapExt($mime);

        $filename = $filename ? $this->sanitizeFilename($filename) : (Str::uuid()->toString() . '.' . $ext);

        if($filename && pathinfo($filename, PATHINFO_EXTENSION) !== $ext) {
            throw new InvalidMimeException("Filename extensions must be .$ext form mime $mime");
        }

        $path = trim($dir . $filename, '/');
        $this->disk->put($path, $bytes);

        return new UploadResult(
            path: $path,
            url: $this->url($path),
            disk: $this->diskName(),
            mime: $mime,
            size: strlen($bytes)
        );
    }

    public function remove(?string $path): bool
    {
        if($path && $this->disk->exists($path)) {
            return $this->disk->delete($path);
        }
        return true;
    }

    public function url(string $path): string
    {
        if(method_exists($this->disk, 'url')) {
            try {
                $publicUrl = $this->disk->url($path);
                if($publicUrl && !Str::startsWith($publicUrl, '/storage/')) {
                    return $publicUrl;
                }
            } catch (\Throwable) {}
        }

        $ttl = Carbon::now()->addMinutes((int)Config::get('filekit.signed_url_ttl', 60));
        return URL::temporarySignedRoute('filekit.show', $ttl, ['path' => $path]);
    }

    /**
     * Переместить/переименовать файл внутри текущего диска.
     *
     * @param  string      $from          Текущий путь (относительно диска)
     * @param  string|null $toDirectory   Новая директория (относительно $baseDir). Если null — остаётся текущая директория $from
     * @param  string|null $toFilename    Новое имя файла. Если null — сохраняем исходное имя
     * @param  bool        $overwrite     Перезаписывать, если файл уже существует по месту назначения
     * @return UploadResult
     */
    public function move(string $from, ?string $toDirectory = null, ?string $toFilename = null, bool $overwrite = false): UploadResult
    {
        $from = ltrim($from, '/');

        if (!$this->disk->exists($from)) {
            throw new \RuntimeException("Source file '{$from}' does not exist.");
        }

        // Куда перемещаем: если директория не задана — оставляем текущую
        $destDir = $toDirectory === null
            ? (trim(dirname($from), '/') . '/')
            : $this->safeJoin($this->baseDir, $toDirectory);

        $destFilename = $toFilename ? $this->sanitizeFilename($toFilename) : basename($from);
        $to = trim($destDir . $destFilename, '/');

        if (!$overwrite && $this->disk->exists($to)) {
            throw new \RuntimeException("Destination '{$to}' already exists.");
        }

        // Если перезаписываем — удалим существующий файл заранее, чтобы избежать ошибок
        if ($overwrite && $this->disk->exists($to)) {
            $this->disk->delete($to);
        }

        // Пытаемся выполнить "native" move; если адаптер не умеет — копируем и удаляем
        try {
            if (method_exists($this->disk, 'move')) {
                $moved = $this->disk->move($from, $to);
                if ($moved === false) {
                    $this->disk->put($to, $this->disk->get($from));
                    $this->disk->delete($from);
                }
            } else {
                $this->disk->put($to, $this->disk->get($from));
                $this->disk->delete($from);
            }
        } catch (\Throwable $e) {
            // Бест-эффорт откат, если вдруг остались "обрывки"
            if ($this->disk->exists($to) && !$this->disk->exists($from)) {
                try { $this->disk->move($to, $from); } catch (\Throwable) {}
            }
            throw $e;
        }

        // Сформируем метаданные результата
        $bytes = $this->disk->get($to);
        $mime = $this->guessBufferMime($bytes);

        return new UploadResult(
            path: $to,
            url: $this->url($to),
            disk: $this->diskName(),
            mime: $mime,
            size: strlen($bytes)
        );
    }

    /**
     * Заменить старый файл новым: грузим новый, удаляем старый, при неудачной очистке — откат.
     *
     * @param  string|null              $oldPath    Путь старого файла (если null — просто загрузка нового)
     * @param  string|UploadedFile      $newFile    Источник нового файла (как и в upload)
     * @param  string|null              $filename   Имя для нового файла (если null — UUID + расширение)
     * @param  string|null              $directory  Директория (относительно $baseDir). Если null — попытка взять ту же директорию, где лежал $oldPath
     * @param  bool                     $failIfRemoveFails  Бросать ли ошибку, если удаление старого файла не удалось (по умолчанию true)
     * @return UploadResult
     */
    public function change(?string $oldPath, string|UploadedFile $newFile, ?string $filename = null, ?string $directory = null, bool $failIfRemoveFails = true): UploadResult
    {
        // Если директория не указана — постараемся сохранить в ту же, где лежал старый файл (если она внутри baseDir)
        if ($directory === null && $oldPath) {
            $oldDir = trim(dirname(trim($oldPath, '/')), '/');
            $base   = trim($this->baseDir, '/');

            if (Str::startsWith($oldDir, $base)) {
                $relative = trim(Str::after($oldDir, $base), '/');
                $directory = $relative !== '' ? $relative : null;
            }
        }

        // 1) Загружаем новый
        $new = $this->upload($newFile, $filename, $directory);

        // 2) Удаляем старый (если был)
        if ($oldPath) {
            $deleted = $this->remove($oldPath);

            if (!$deleted && $failIfRemoveFails) {
                // Откат: удаляем только что загруженный новый файл и кидаем ошибку
                $this->remove($new->path);
                throw new \RuntimeException("Failed to delete old file '{$oldPath}'. New file upload has been rolled back.");
            }
        }

        return $new;
    }

    protected function diskName(): string
    {
        return (string) Config::get('filekit.disk', 'public');
    }

    /** @return array{0:string,1:string} [bytes, mime] */
    protected function readInput(string|UploadedFile $input): array
    {
        if($input instanceof UploadedFile) {
            $bytes = file_get_contents($input->getRealPath());
            $this->assertSize($bytes);
            $mime = $input->getMimeType() ?? $this->guessBufferMime($bytes);
            return [$bytes, $mime];
        }

        if(is_file($input)) {
            $bytes = file_get_contents($input);
            $this->assertSize($bytes);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($input) ?: $this->guessBufferMime($bytes);
            return [$bytes, $mime];
        }

        if(preg_match('/^data:(.*?);base64,/', $input, $m)) {
            $bytes = base64_decode(substr($input, strpos($input, ',') + 1));
            $this->assertSize($bytes);
            $mime = $m[1] ?: $this->guessBufferMime($bytes);
            return [$bytes, $mime];
        }

        if(filter_var($input, FILTER_VALIDATE_URL)) {
            $resp = Http::timeout(10)->get($input);
            $resp->throw();
            $bytes = (string) $resp->body();
            $this->assertSize($bytes);
            $mime = $resp->header('Content-Type') ?: $this->guessBufferMime($bytes);
            return [$bytes, $mime];
        }

        $bytes = (string) $input;
        $this->assertSize($bytes);
        $mime = $this->guessBufferMime($bytes);
        return [$bytes, $mime];
    }

    protected function guessBufferMime(string $bytes): string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
    }

    protected function assertSize(string $bytes): void
    {
        if(strlen($bytes) > $this->maxSize) {
            throw new InvalidMimeException('File is too large.');
        }
    }

    protected function assertAllowed(string $mime): void
    {
        if(!in_array($mime, $this->allowedMimes, true)) {
            throw new InvalidMimeException("Mime '$mime' is not allowed.");
        }
    }

    protected function mapExt(string $mime): string
    {
        $map = MimeMap::map();
        if(!isset($map[$mime])) {
            throw new InvalidMimeException("Unknown extension for mime '$mime'");
        }
        return $map[$mime];
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = str_replace(["\0", "\r", "\n"], '', $name);
        $name = preg_replace('/[^\w.\-]/u', '_', $name);
        return ltrim($name, '.');
    }

    protected function safeJoin(string $base, string $segment): string
    {
        $s = trim($segment, '/');
        if($s === '') return $base;
        if(str_contains($s, '..') || str_starts_with($s, '/')) {
            throw new UnsafePathException('Unsafe directory segment.');
        }
        return trim($base . '/' . $s . '/');
    }
}
