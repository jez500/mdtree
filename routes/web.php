<?php

use App\Http\Controllers\BrowserController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/browser')->name('home');
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::get('/browser', [BrowserController::class, 'index'])->name('browser.index');
    Route::get('/browser/{workspace}/links', [BrowserController::class, 'resolveLink'])->name('browser.links.resolve');
    Route::get('/browser/{workspace}/search', [BrowserController::class, 'search'])->name('browser.search');
    Route::get('/browser/{workspace}', [BrowserController::class, 'show'])->name('browser.show');

    Route::put('/api/files/{workspace}', [FileController::class, 'save'])->name('files.save');
    Route::post('/api/files/{workspace}/create', [FileController::class, 'createFile'])->name('files.create');
    Route::delete('/api/files/{workspace}', [FileController::class, 'deleteFile'])->name('files.delete');
    Route::post('/api/directories/{workspace}', [FileController::class, 'createDirectory'])->name('directories.create');
    Route::patch('/api/files/{workspace}', [FileController::class, 'moveNode'])->name('files.move');
});

require __DIR__.'/settings.php';
