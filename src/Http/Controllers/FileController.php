<?php

namespace EduVl\FileKit\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileController extends Controller
{
    public function show(string $path)
    {
        $disk = Config::get('filekit.disk', 'public');
        if(!Storage::disk($disk)->exists($path)) {
            return new NotFoundHttpException('File not found');
        }
        return Storage::disk($disk)->response($path);
    }

    public function download(string $path)
    {
        $disk = Config::get('filekit.disk', 'public');
        if(!Storage::disk($disk)->exists($path)) {
            return new NotFoundHttpException('File not found');
        }
        return Storage::disk($disk)->download($path);
    }
}