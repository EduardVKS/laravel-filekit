<?php

return [
    'disk' => env('FILEKIT_DISK', 'public'),

    'base_dirs' => [
        'files' => 'uploads',
        'images' => 'uploads/images',
        'audio' => 'uploads/audio',
        'video' => 'uploads/video'
    ],

    'signed_url_ttl' => env('FILEKIT_SIGNED_TTL', 60),

    'max_size_bytes' => env('FILEKIT_MAX_SIZE', 50 * 1024 * 1024), // 50MB

    'allowed' => [
        'images' => [
            'image/jpeg','image/png','image/gif','image/bmp','image/webp',
            'image/svg+xml','image/x-icon','image/tiff','image/heic','image/heif',
        ],
        'audio' => [
            'audio/mpeg','audio/wav','audio/ogg','audio/webm','audio/aac',
            'audio/flac','audio/midi','audio/amr',
        ],
        'files' => [
            'application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/rtf','application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation',
            'application/epub+zip','text/plain','application/json','text/csv',
            'application/zip','application/x-7z-compressed','application/x-rar-compressed',
            'application/x-tar','application/gzip','application/x-bzip2',
            'image/jpeg','image/png','image/webp',
            'video/mp4','video/webm','video/quicktime',
        ],
    ],
];