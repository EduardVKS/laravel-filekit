<?php

namespace EduVl\FileKit\Support;

final class MimeMap
{
    /** @return array<string,string> */
    public static function map(): array
    {
        return [
            // документы
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/rtf' => 'rtf',
            'application/vnd.oasis.opendocument.text' => 'odt',
            'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
            'application/vnd.oasis.opendocument.presentation' => 'odp',
            'application/epub+zip' => 'epub',
            'text/plain' => 'txt',
            'application/json' => 'json',
            'text/csv' => 'csv',

            // изображения
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'image/tiff' => 'tiff',
            'image/heic' => 'heic',
            'image/heif' => 'heif',

            // аудио
            'audio/mpeg' => 'mp3',
            'audio/wav'  => 'wav',
            'audio/ogg'  => 'ogg',
            'audio/webm' => 'weba',
            'audio/aac'  => 'aac',
            'audio/flac' => 'flac',
            'audio/midi' => 'midi',
            'audio/amr'  => 'amr',

            // видео
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/mpeg' => 'mpeg',
            'video/ogg' => 'ogv',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',

            // архивы
            'application/zip' => 'zip',
            'application/x-7z-compressed' => '7z',
            'application/x-rar-compressed' => 'rar',
            'application/x-tar' => 'tar',
            'application/gzip' => 'gz',
            'application/x-bzip2' => 'bz2',
        ];
    }
}