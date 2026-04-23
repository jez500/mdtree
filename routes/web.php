<?php

use App\Http\Controllers\BrowserController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/browser')->name('home');

    Route::get('/browser', [BrowserController::class, 'index'])->name('browser.index');
    Route::get('/browser/{workspace}', [BrowserController::class, 'show'])->name('browser.show');

    Route::put('/api/files/{workspace}', [FileController::class, 'save'])->name('files.save');
});

require __DIR__.'/settings.php';
