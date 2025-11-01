<?php

namespace EduVl\FileKit\Contracts;

readonly class UploadResult
{
    public function __construct(
        public string $path,
        public string $url,
        public string $disk,
        public string $mime,
        public int    $size
    ) {}

}