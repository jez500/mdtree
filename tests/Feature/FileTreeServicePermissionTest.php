<?php

use App\Services\FileTreeService;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/mdtree_perm_'.uniqid();
    mkdir($this->root.'/readable', 0755, true);
    mkdir($this->root.'/locked', 0755, true);

    file_put_contents($this->root.'/top.md', '# top');
    file_put_contents($this->root.'/readable/inner.md', '# inner');
    file_put_contents($this->root.'/locked/secret.md', '# secret top');

    // Make the directory unreadable, mimicking a volume mount we lack access to.
    chmod($this->root.'/locked', 0000);

    if (is_readable($this->root.'/locked')) {
        chmod($this->root.'/locked', 0755);
        $this->markTestSkipped('Cannot make a directory unreadable (likely running as root).');
    }
});

afterEach(function () {
    chmod($this->root.'/locked', 0755);
    exec('rm -rf '.escapeshellarg($this->root));
});

it('builds the tree without dying on an unreadable directory', function () {
    $service = new FileTreeService;

    $tree = $service->tree($this->root, ['md']);

    $names = collect($tree)->pluck('name');

    expect($names)->toContain('top.md')
        ->and($names)->toContain('readable')
        ->and($names)->not->toContain('locked');
});

it('searches without dying on an unreadable directory', function () {
    $service = new FileTreeService;

    $results = $service->search($this->root, ['md'], 'top');

    expect(collect($results)->pluck('path'))
        ->toContain('top.md')
        ->not->toContain('locked/secret.md');
});
