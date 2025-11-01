<?php

namespace EduVl\FileKit;

use Illuminate\Support\ServiceProvider;
use EduVl\FileKit\Services\UploadManager;

class FileKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filekit.php', 'filekit');

        $this->app->singleton(UploadManager::class, function ($app) {
            return new UploadManager(
                $app['files'],          // Illuminate\Filesystem\Filesystem
                $app['filesystem'],     // Illuminate\Filesystem\FilesystemManager
                $app['config']->get('filekit', [])
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $target = method_exists($this->app, 'configPath')
                ? $this->app->configPath('filekit.php')
                : (method_exists($this->app, 'basePath')
                    ? $this->app->basePath('config/filekit.php')
                    : getcwd().DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'filekit.php');

            $this->publishes([
                __DIR__.'/../config/filekit.php' => $target,
            ], 'filekit-config');
        }

        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}