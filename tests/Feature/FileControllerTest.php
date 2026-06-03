<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->withoutVite();

    $this->workspaceDir = sys_get_temp_dir().'/mdtree_test_'.uniqid();
    mkdir($this->workspaceDir, 0755, true);

    Config::set('mdtree.workspaces', [
        'test' => ['name' => 'Test', 'path' => $this->workspaceDir],
    ]);
    Config::set('mdtree.extensions', ['md', 'txt']);

    Cache::flush();

    Workspace::create([
        'slug' => 'test',
        'name' => 'Test',
        'path' => $this->workspaceDir,
    ]);

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
    Cache::shouldReceive('forget')->once();

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
    Cache::shouldReceive('forget')->once();

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

    Cache::shouldReceive('forget')->once();

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

    Cache::shouldReceive('forget')->once();

    $this->patchJson('/api/files/test', [
        'from' => 'notes.md',
        'to' => 'archive/notes.md',
    ])
        ->assertOk()
        ->assertJson(['moved' => true]);

    expect(file_exists($this->workspaceDir.'/notes.md'))->toBeFalse();
    expect(file_exists($this->workspaceDir.'/archive/notes.md'))->toBeTrue();
});

test('node move updates markdown links to moved files', function () {
    mkdir($this->workspaceDir.'/notes', 0755, true);
    mkdir($this->workspaceDir.'/archive', 0755, true);
    file_put_contents($this->workspaceDir.'/index.md', '[Notes](notes/notes.md)');
    file_put_contents($this->workspaceDir.'/notes/notes.md', '# Notes');

    $this->patchJson('/api/files/test', [
        'from' => 'notes/notes.md',
        'to' => 'archive/notes.md',
    ])->assertOk();

    expect(file_get_contents($this->workspaceDir.'/index.md'))->toBe('[Notes](archive/notes.md)');
});

test('node move updates links inside moved markdown files', function () {
    mkdir($this->workspaceDir.'/notes', 0755, true);
    mkdir($this->workspaceDir.'/archive', 0755, true);
    file_put_contents($this->workspaceDir.'/notes/ref.md', '# Reference');
    file_put_contents($this->workspaceDir.'/notes/notes.md', '[Reference](ref.md)');

    $this->patchJson('/api/files/test', [
        'from' => 'notes/notes.md',
        'to' => 'archive/notes.md',
    ])->assertOk();

    expect(file_get_contents($this->workspaceDir.'/archive/notes.md'))->toBe('[Reference](../notes/ref.md)');
});

test('node move updates markdown links for moved directories', function () {
    mkdir($this->workspaceDir.'/notes/deep', 0755, true);
    mkdir($this->workspaceDir.'/archive', 0755, true);
    file_put_contents($this->workspaceDir.'/index.md', '[Notes](notes/deep/notes.md)');
    file_put_contents($this->workspaceDir.'/notes/deep/notes.md', '[Index](../../index.md)');

    $this->patchJson('/api/files/test', [
        'from' => 'notes',
        'to' => 'archive/notes',
    ])->assertOk();

    expect(file_get_contents($this->workspaceDir.'/index.md'))->toBe('[Notes](archive/notes/deep/notes.md)');
    expect(file_get_contents($this->workspaceDir.'/archive/notes/deep/notes.md'))->toBe('[Index](../../../index.md)');
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
