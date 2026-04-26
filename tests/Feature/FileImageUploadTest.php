<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->workspaceDir = sys_get_temp_dir().'/mdtree_images_test_'.uniqid();
    mkdir($this->workspaceDir, 0755, true);

    Config::set('mdtree.workspaces', [
        'test' => ['name' => 'Test', 'path' => $this->workspaceDir],
    ]);

    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->workspaceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($this->workspaceDir);
});

test('image upload stores images in workspace assets directory', function () {
    $response = $this->postJson('/api/files/test/images', [
        'current_path' => 'notes/current.md',
        'image' => UploadedFile::fake()->create('Screenshot One.png', 4, 'image/png'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('src', fn (string $src): bool => str_starts_with($src, '../assets/screenshot-one-'))
        ->assertJsonPath('path', fn (string $path): bool => str_starts_with($path, 'assets/screenshot-one-'));

    expect(glob($this->workspaceDir.'/assets/screenshot-one-*.png'))->toHaveCount(1);
});

test('image asset route serves files from workspace assets directory', function () {
    mkdir($this->workspaceDir.'/assets', 0755, true);
    file_put_contents(
        $this->workspaceDir.'/assets/example.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
    );

    $this->get('/api/assets/test?path=assets/example.png')
        ->assertOk();
});

test('image upload rejects non images', function () {
    $this->postJson('/api/files/test/images', [
        'current_path' => 'current.md',
        'image' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
    ])->assertUnprocessable();
});

test('image asset route rejects paths outside assets directory', function () {
    file_put_contents($this->workspaceDir.'/secret.png', 'secret');

    $this->get('/api/assets/test?path=secret.png')->assertNotFound();
});
