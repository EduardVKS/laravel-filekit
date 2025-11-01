<?php

namespace EduVl\FileKit\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;

class UploadManager
{
    public function __construct(
        protected Filesystem $file,
        protected FilesystemManager $storage,
        protected array $config
    ) {}

    public function images(): ImageService
    {
        return new ImageService(
            disk: $this->storage->disk($this->config['disk']),
            baseDir: $this->config['base_dirs']['images'],
            allowedMimes: $this->config['allowed']['images'],
            maxSize: (int) $this->config['max_size_bytes']
        );
    }
    public function audio(): AudioService
    {
        return new AudioService(
            disk: $this->storage->disk($this->config['disk']),
            baseDir: $this->config['base_dirs']['audio'],
            allowedMimes: $this->config['allowed']['audio'],
            maxSize: (int) $this->config['max_size_bytes']
        );
    }

    public function video(): VideoService
    {
        return new VideoService(
            disk: $this->storage->disk($this->config['disk']),
            baseDir: $this->config['base_dirs']['video'],
            allowedMimes: $this->config['allowed']['video'],
            maxSize: (int) $this->config['max_size_bytes']
        );
    }

    public function files(): FileService
    {
        return new class (
            $this->storage->disk($this->config['disk']),
            $this->config['base_dirs']['files'],
            $this->config['base_dirs']['files'],
            (int) $this->config['max_size_bytes']
        ) extends FileService {};
    }
}
