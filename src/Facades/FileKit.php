<?php

namespace EduVl\FileKit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \EduVl\FileKit\Services\ImageService images()
 * @method static \EduVl\FileKit\Services\AudioService audio()
 * @method static \EduVl\FileKit\Services\VideoService video()
 * @method static \EduVl\FileKit\Services\FileService files()
 * @method static \EduVl\FileKit\Services\FileService url()
 */
class FileKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EduVl\FileKit\Services\UploadManager::class;
    }
}
