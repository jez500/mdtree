<?php

use App\Models\User;
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

    Cache::shouldReceive('remember')
        ->zeroOrMoreTimes()
        ->andReturnUsing(fn (string $key, mixed $ttl, callable $callback) => $callback());

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

test('browser show supports partial reloads without reloading the tree', function () {
    file_put_contents($this->workspaceDir.'/hello.md', '# Hello World');

    Cache::shouldReceive('remember')
        ->zeroOrMoreTimes()
        ->andReturnUsing(fn (string $key, mixed $ttl, callable $callback) => $callback());

    $this->get('/browser/test?path=hello.md')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tree')
            ->reloadOnly(['filePath', 'fileContent'], fn ($reload) => $reload
                ->missing('tree')
                ->where('filePath', 'hello.md')
                ->where('fileContent', '# Hello World')
            )
        );
});

test('browser show opens readme by default', function () {
    file_put_contents($this->workspaceDir.'/readme.md', '# Read Me');

    $this->get('/browser/test')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filePath', 'readme.md')
            ->where('fileContent', '# Read Me')
        );
});

test('browser show opens readme case insensitively', function () {
    file_put_contents($this->workspaceDir.'/README.MD', '# Read Me');

    $this->get('/browser/test')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filePath', 'README.MD')
            ->where('fileContent', '# Read Me')
        );
});

test('browser show respects explicit path over readme', function () {
    file_put_contents($this->workspaceDir.'/readme.md', '# Read Me');
    file_put_contents($this->workspaceDir.'/notes.md', '# Notes');

    $this->get('/browser/test?path=notes.md')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filePath', 'notes.md')
            ->where('fileContent', '# Notes')
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

test('browser resolves relative markdown links to files', function () {
    mkdir($this->workspaceDir.'/notes', 0755, true);
    file_put_contents($this->workspaceDir.'/notes/source.md', '[Target](../target.md)');
    file_put_contents($this->workspaceDir.'/target.md', '# Target');

    $this->get('/browser/test/links?from=notes/source.md&href=../target.md')
        ->assertRedirect('/browser/test?path=target.md');
});

test('browser link resolver rejects external links', function () {
    file_put_contents($this->workspaceDir.'/source.md', '[External](https://example.com)');

    $this->get('/browser/test/links?from=source.md&href=https://example.com')
        ->assertNotFound();
});

test('browser search ranks title matches above body matches', function () {
    file_put_contents($this->workspaceDir.'/alpha.md', 'Only body mentions zebra.');
    file_put_contents($this->workspaceDir.'/zebra-notes.md', 'No matching body.');

    $this->getJson('/browser/test/search?q=zebra')
        ->assertOk()
        ->assertJsonPath('results.0.path', 'zebra-notes.md')
        ->assertJsonPath('results.1.path', 'alpha.md');
});

test('browser search only scans registered extensions', function () {
    file_put_contents($this->workspaceDir.'/notes.md', 'needle');
    file_put_contents($this->workspaceDir.'/image.png', 'needle');

    $this->getJson('/browser/test/search?q=needle')
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.path', 'notes.md');
});

test('browser search returns matching document metadata', function () {
    mkdir($this->workspaceDir.'/nested', 0755, true);
    file_put_contents($this->workspaceDir.'/nested/notes.md', '# Notes'.PHP_EOL.PHP_EOL.'A searchable phrase lives here.');

    $this->getJson('/browser/test/search?q=searchable')
        ->assertOk()
        ->assertJsonPath('results.0.title', 'notes')
        ->assertJsonPath('results.0.path', 'nested/notes.md')
        ->assertJson(fn ($json) => $json
            ->has('results.0.excerpt')
            ->etc()
        );
});

test('browser search requires authentication', function () {
    auth()->logout();

    $this->get('/browser/test/search?q=notes')->assertRedirectToRoute('login');
});

test('browser show returns 404 for unknown workspace', function () {
    $this->get('/browser/nonexistent')->assertNotFound();
});

test('browser search returns 404 for unknown workspace', function () {
    $this->getJson('/browser/nonexistent/search?q=notes')->assertNotFound();
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
