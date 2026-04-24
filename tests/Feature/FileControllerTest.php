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

test('file create creates a file', function () {
    $this->postJson('/api/files/test/create', [
        'path' => 'notes/new.md',
    ])
        ->assertOk()
        ->assertJson(['created' => true]);

    expect(file_exists($this->workspaceDir.'/notes/new.md'))->toBeTrue();
});

test('file create rejects traversal attempts', function () {
    $this->postJson('/api/files/test/create', [
        'path' => '../../etc/passwd',
    ])->assertUnprocessable();
});

test('file create rejects existing files', function () {
    file_put_contents($this->workspaceDir.'/notes.md', '# Notes');

    $this->postJson('/api/files/test/create', [
        'path' => 'notes.md',
    ])->assertUnprocessable();
});

test('directory create creates a directory', function () {
    $this->postJson('/api/directories/test', [
        'path' => 'notes/archive',
    ])
        ->assertOk()
        ->assertJson(['created' => true]);

    expect(is_dir($this->workspaceDir.'/notes/archive'))->toBeTrue();
});

test('directory create rejects traversal attempts', function () {
    $this->postJson('/api/directories/test', [
        'path' => '../../etc/passwd',
    ])->assertUnprocessable();
});

test('file delete deletes a file', function () {
    file_put_contents($this->workspaceDir.'/notes.md', '# Notes');

    $this->deleteJson('/api/files/test', [
        'path' => 'notes.md',
    ])
        ->assertOk()
        ->assertJson(['deleted' => true]);

    expect(file_exists($this->workspaceDir.'/notes.md'))->toBeFalse();
});

test('file delete rejects traversal attempts', function () {
    $this->deleteJson('/api/files/test', [
        'path' => '../../etc/passwd',
    ])->assertUnprocessable();
});

test('node move moves a file', function () {
    file_put_contents($this->workspaceDir.'/notes.md', '# Notes');
    mkdir($this->workspaceDir.'/archive', 0755, true);

    $this->patchJson('/api/files/test', [
        'from' => 'notes.md',
        'to' => 'archive/notes.md',
    ])
        ->assertOk()
        ->assertJson(['moved' => true]);

    expect(file_exists($this->workspaceDir.'/notes.md'))->toBeFalse();
    expect(file_exists($this->workspaceDir.'/archive/notes.md'))->toBeTrue();
});

test('node move rejects traversal attempts', function () {
    file_put_contents($this->workspaceDir.'/notes.md', '# Notes');

    $this->patchJson('/api/files/test', [
        'from' => 'notes.md',
        'to' => '../../etc/passwd',
    ])->assertUnprocessable();
});

test('node move rejects moving a folder into its subtree', function () {
    mkdir($this->workspaceDir.'/archive/child', 0755, true);

    $this->patchJson('/api/files/test', [
        'from' => 'archive',
        'to' => 'archive/child/archive',
    ])->assertUnprocessable();
});
