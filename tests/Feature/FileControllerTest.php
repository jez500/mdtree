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

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
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

test('file save saves content to filesystem', function () {
    file_put_contents($this->workspaceDir.'/test.md', '# Original');

    $this->put('/api/files/test', [
        'path' => 'test.md',
        'content' => '# Updated Content',
    ])
        ->assertOk()
        ->assertJson(['saved' => true]);

    expect(file_get_contents($this->workspaceDir.'/test.md'))->toBe('# Updated Content');
});

test('file save returns 404 for unknown workspace', function () {
    $this->put('/api/files/nonexistent', [
        'path' => 'test.md',
        'content' => '# Content',
    ])->assertNotFound();
});

test('file save returns 422 for missing path', function () {
    $this->putJson('/api/files/test', [
        'content' => '# Content',
    ])->assertUnprocessable();
});

test('file save returns 422 for missing content', function () {
    $this->putJson('/api/files/test', [
        'path' => 'test.md',
    ])->assertUnprocessable();
});

test('file save returns 422 for path traversal attempt', function () {
    $this->put('/api/files/test', [
        'path' => '../../etc/passwd',
        'content' => 'malicious content',
    ])->assertUnprocessable();
});

test('file save returns 422 for non-existent file', function () {
    $this->put('/api/files/test', [
        'path' => 'does-not-exist.md',
        'content' => '# Content',
    ])->assertUnprocessable();
});

test('file save requires authentication', function () {
    auth()->logout();

    $this->put('/api/files/test', [
        'path' => 'test.md',
        'content' => '# Content',
    ])->assertRedirectToRoute('login');
});
