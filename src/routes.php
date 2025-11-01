<?php

use Illuminate\Support\Facades\Route;
use EduVl\FileKit\Http\Controllers\FileController;

Route::middleware('web')->group(function() {
    Route::get('/filekit/show/{path}', [FileController::class, 'show'])
        ->where('path', '.*')
        ->name('filekit.show')
        ->middleware('signed');

    Route::get('/filekit/download/{path}', [FileController::class, 'download'])
        ->where('path', '.*')
        ->name('filekit.download')
        ->middleware('signed');
});
