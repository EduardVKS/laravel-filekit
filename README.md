# Laravel FileKit

> Typed upload services for Laravel: **images**, **audio**, **video** and **generic files** with safe paths, MIME whitelists, and **signed URLs** for private disks.

[![PHP](https://img.shields.io/badge/PHP-^8.2-777bb3?logo=php)](#requirements)
[![Laravel](https://img.shields.io/badge/Laravel-10%20%7C%2011-ff2d20?logo=laravel)](#requirements)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

---

## ✨ Features

- ✅ Upload from `UploadedFile`, **local path**, **HTTP URL**, **data:base64**, or **raw bytes**
- ✅ Per-type **MIME whitelists** and **size limits**
- ✅ **Signed routes** for private disks (`filekit.show`, `filekit.download`)
- ✅ Clean result object: `UploadResult { path, url, disk, mime, size }`
- ✅ Extensible (add your own `VideoService`, etc.)
- ✅ Laravel **auto-discovery** and publishable config

---

## 📚 Contents
- [Requirements](#requirements)
- [Installation](#installation)
- [Publish Config](#publish-config)
- [Quick Start](#quick-start)
- [API](#api)
- [Signed URLs](#signed-urls)
- [Validation](#validation)
- [Extending](#extending)
- [Security Notes](#security-notes)
- [Testing (Testbench)](#testing-testbench)
- [Troubleshooting](#troubleshooting)
- [Versioning](#versioning)
- [Contributing](#contributing)
- [License](#license)

---

<a id="requirements"></a>
## ✅ Requirements

| Component | Version |
|---|---|
| PHP | ^8.2 / ^8.3 / ^8.4 |
| Laravel | 10.x or 11.x |
| Guzzle | ^7.8 (installed automatically) |

---

<a id="installation"></a>
## 📦 Installation

### Packagist (when published)
```bash
composer require eduvl/laravel-filekit
```

### From GitHub (VCS)
Add to your app’s `composer.json`:
```json
"repositories": [
{ "type": "vcs", "url": "https://github.com/eduvl/laravel-filekit" }
]
```
Then:
```bash
composer require eduvl/laravel-filekit:*@dev
```

### Local path (for development)
```json
"repositories": [
{ "type": "path", "url": "../laravel-filekit", "options": { "symlink": true } }
]
```
```bash
composer require eduvl/laravel-filekit:*@dev
```

> Uses Laravel auto-discovery — no manual provider registration.

---

<a id="publish-config"></a>
## ⚙️ Publish Config

```bash
php artisan vendor:publish --provider="EduVl\FileKit\FileKitServiceProvider" --tag=filekit-config
```

This creates `config/filekit.php`:

```php
return [
'disk' => env('FILEKIT_DISK', 'public'),

    'base_dirs' => [
        'files'  => 'uploads',
        'images' => 'uploads/images',
        'audio'  => 'uploads/audio',
    ],

    'signed_url_ttl' => env('FILEKIT_SIGNED_TTL', 60), // minutes
    'max_size_bytes' => env('FILEKIT_MAX_SIZE', 50 * 1024 * 1024),

    'allowed' => [
        'images' => [
            'image/jpeg','image/png','image/gif','image/bmp','image/webp',
            'image/svg+xml','image/x-icon','image/tiff','image/heic','image/heif',
        ],
        'audio'  => [
            'audio/mpeg','audio/wav','audio/ogg','audio/webm','audio/aac',
            'audio/flac','audio/midi','audio/amr',
        ],
        'files'  => [
            'application/pdf','application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/rtf','application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'application/epub+zip','text/plain','application/json','text/csv',
            'application/zip','application/x-7z-compressed','application/x-rar-compressed',
            'application/x-tar','application/gzip','application/x-bzip2',
            'image/jpeg','image/png','image/webp',
            'video/mp4','video/webm','video/quicktime',
        ],
    ],
];
```

> Using the local **public** disk? Run:
> ```bash
> php artisan storage:link
> ```

---

<a id="quick-start"></a>
## 🚀 Quick Start

```php
use Illuminate\Http\Request;
use FileKit;

class ProfilePhotoController
{
public function store(Request $request)
{
$request->validate([
'photo' => ['required','image','max:5120'], // 5MB
]);

        $res = FileKit::images()->upload(
            $request->file('photo'),
            directory: 'users/'.auth()->id()
        );

        return response()->json([
            'path' => $res->path, // uploads/images/users/123/uuid.jpg
            'url'  => $res->url,  // public or signed URL
            'mime' => $res->mime, // image/jpeg
            'size' => $res->size, // bytes
        ]);
    }
}
```

---

<a id="api"></a>
## 🧰 API

Facade → manager → services:
```php
FileKit::images(); // ImageService
FileKit::audio();  // AudioService
FileKit::video();  // VideoService
FileKit::files();  // Generic FileService
```

### Upload
```php
/** @var \EduVl\FileKit\Contracts\UploadResult $res */
$res = FileKit::images()->upload(
$input,               // UploadedFile | string (see below)
filename: null,       // optional, e.g. "avatar.jpg"
directory: 'users/42' // subfolder appended to base_dir
);
```

**Allowed `$input` types**
- `UploadedFile` (form upload)
- Local **path** string (`/tmp/x.png`)
- **HTTP URL** (`https://…`) — downloaded via Laravel HTTP Client
- **Data URL** (`data:image/png;base64,…`)
- **Raw bytes** string

### Result object
```php
$res->path; // disk-relative path
$res->url;  // public URL or temporary signed route
$res->disk; // disk name
$res->mime; // MIME type
$res->size; // bytes
```

### Delete
```php
FileKit::files()->remove($res->path); // true (also true if already missing)
```

### Moving and Copying Files

You can move (rename) or copy files inside the same storage disk.

```php
use EduVl\FileKit\Services\FileService;

/** @var FileService $files */
$files = app(FileService::class);

$result = $files->move(
    from: 'uploads/tmp/photo.jpg',
    toDirectory: 'avatars',
    toFilename: 'user-10.jpg',
    overwrite: true
);

$result = $files->copy(
    from: 'uploads/tmp/photo.jpg',
    toDirectory: 'avatars',
    toFilename: 'user-10.jpg',
    overwrite: true
);

echo $result->path; // avatars/user-10.jpg
echo $result->url;  // public url or signed route

---

<a id="signed-urls"></a>
## 🔏 Signed URLs

- **Public** disk → direct `Storage::url($path)`.
- **Private** disk → temporary signed routes:
    - `filekit.show` — inline stream
    - `filekit.download` — attachment

TTL is `filekit.signed_url_ttl` (minutes).

Manual download URL:
```php
use Illuminate\Support\Facades\URL;

$url = URL::temporarySignedRoute(
'filekit.download',
now()->addMinutes(60),
['path' => $res->path]
);
```

---

<a id="validation"></a>
## ✅ Validation

Still validate requests at the controller level:

```php
$request->validate([
'file' => ['required', 'mimetypes:image/jpeg,image/png', 'max:10240'] // 10MB
]);
```

The package also enforces its own MIME whitelist and size caps.

---

<a id="extending"></a>
## 🧩 Extending

Example: add **ExcelService** in your app:

```php
namespace App\Services;

use EduVl\FileKit\Services\FileService;

class ExcelService extends FileService {}
```

- Add Exel MIME list and base dir in `config/filekit.php`.
- Optionally expose a factory method in your own manager wrapper.

---

<a id="security-notes"></a>
## 🔐 Security Notes

- **MIME whitelists** per type
- Filename **sanitization** (no dangerous chars)
- No `../` or absolute paths allowed in `directory`
- **Size limit** (`max_size_bytes`)
- HTTP downloads via Laravel HTTP Client (with timeout)  
  **Recommended**: domain allow-list, block local/loopback IPs (SSRF), verify `Content-Length`.

---

<a id="texting"></a>
## 🧪 Testing (Testbench)

This package targets Laravel; use **Orchestra Testbench** for package tests.

Install (in the package repo):
```bash
composer require --dev "orchestra/testbench:^9.0"
```

`tests/TestCase.php`
```php
<?php

namespace EduVl\FileKit\Tests;

use EduVl\FileKit\FileKitServiceProvider;
use Orchestra\Testbench\TestCase as Base;

abstract class TestCase extends Base
{
    protected function getPackageProviders($app)
    {
        return [FileKitServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('filekit.disk', 'public');
    }
}
```

Route test example:
```php
<?php

namespace EduVl\FileKit\Tests;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class RoutesTest extends TestCase
{
    public function test_signed_show_serves_file()
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/test.txt', 'hello');

        $url = URL::temporarySignedRoute('filekit.show', now()->addMinutes(5), [
            'path' => 'uploads/test.txt',
        ]);

        $this->get($url)->assertOk()->assertSee('hello');
    }
}
```

Run:
```bash
vendor/bin/phpunit
```

---

<a id="troubleshooting"></a>
## 🛠️ Troubleshooting

- **Windows & `|` in Composer constraints**  
  On `cmd.exe` use ranges instead of pipes:  
  `"illuminate/support:>=10.0 <12.0"`.

- **404 when accessing URL**  
  Ensure the file exists on the configured disk and the path matches.  
  For public disk: `php artisan storage:link`.

- **IDE highlights helpers in the package**  
  Outside a Laravel app those helpers aren’t loaded — it’s fine.  
  Keeping **Testbench** in `require-dev` restores helper awareness for IDE/tests.

---

<a id="versioning"></a>
## 🔢 Versioning

- `0.x` — development, API may change.
- `1.x` — stable; breaking changes only in `2.0`.

---

<a id="contributing"></a>
## 🤝 Contributing

PRs and issues are welcome!

1. `git clone`
2. `composer install`
3. `vendor/bin/phpunit`
4. Don’t commit `vendor/` or `composer.lock` (libraries usually don’t ship lockfiles)

---

<a id="license"></a>
## 📄 License

**MIT** — see `LICENSE`.