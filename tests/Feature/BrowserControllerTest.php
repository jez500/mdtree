<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->withoutVite();

    $this->workspaceDir = sys_get_temp_dir().'/mdtree_test_'.uniqid();
    mkdir($this->workspaceDir, 0755, true);

    Config::set('mdtree.workspaces', [
        'test' => ['name' => 'Test', 'path' => $this->workspaceDir],
    ]);
    Config::set('mdtree.extensions', ['md', 'txt']);

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

test('root redirects to browser index', function () {
    $this->get('/')->assertRedirect('/browser');
});

test('browser index redirects to first workspace', function () {
    $this->get('/browser')->assertRedirect('/browser/test');
});

test('browser show renders the browser page', function () {
    file_put_contents($this->workspaceDir.'/hello.md', '# Hello');

    $this->get('/browser/test')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Browser')
            ->has('workspace')
            ->has('tree')
            ->where('workspace', 'test')
            ->where('filePath', null)
            ->where('fileContent', null)
        );
});

test('browser show loads file content when path is given', function () {
    file_put_contents($this->workspaceDir.'/hello.md', '# Hello World');

    $this->get('/browser/test?path=hello.md')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Browser')
            ->where('filePath', 'hello.md')
            ->where('fileContent', '# Hello World')
        );
});

test('browser show returns 404 for unknown workspace', function () {
    $this->get('/browser/nonexistent')->assertNotFound();
});

test('browser show returns 404 for path traversal attempt', function () {
    $this->get('/browser/test?path=../../etc/passwd')->assertNotFound();
});

test('browser show returns 404 for unknown file path', function () {
    $this->get('/browser/test?path=does-not-exist.md')->assertNotFound();
});

test('tree excludes dotfiles', function () {
    file_put_contents($this->workspaceDir.'/.hidden.md', '# Hidden');
    file_put_contents($this->workspaceDir.'/visible.md', '# Visible');

    $this->get('/browser/test')
        ->assertInertia(fn ($page) => $page
            ->where('tree.0.name', 'visible.md')
            ->count('tree', 1)
        );
});

test('tree excludes files with disallowed extensions', function () {
    file_put_contents($this->workspaceDir.'/notes.md', '# Notes');
    file_put_contents($this->workspaceDir.'/image.png', '');

    $this->get('/browser/test')
        ->assertInertia(fn ($page) => $page
            ->count('tree', 1)
            ->where('tree.0.name', 'notes.md')
        );
});
