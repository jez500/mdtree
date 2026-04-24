<?php

namespace App\Services;

use DirectoryIterator;

class FileTreeService
{
    /**
     * Build a recursive file tree for a workspace root.
     *
     * @param  string[]  $extensions
     * @return array<int, array{name: string, type: string, path: string, children?: array}>
     */
    public function tree(string $rootPath, array $extensions): array
    {
        $realRoot = realpath($rootPath);

        if ($realRoot === false || ! is_dir($realRoot)) {
            return [];
        }

        return $this->buildTree($realRoot, $realRoot, $extensions);
    }

    /**
     * Read a file from within a workspace root, validating the path to prevent traversal.
     */
    public function readFile(string $rootPath, string $relativePath): ?string
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return null;
        }

        $candidate = $realRoot.DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');
        $realFile = realpath($candidate);

        if ($realFile === false || ! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! is_file($realFile)) {
            return null;
        }

        return file_get_contents($realFile) ?: '';
    }

    /**
     * Write a file within a workspace root, validating the path to prevent traversal.
     */
    public function writeFile(string $rootPath, string $relativePath, string $content): bool
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return false;
        }

        $realFile = $this->resolveNewPath($realRoot, $relativePath);

        if ($realFile === null) {
            return false;
        }

        if (! is_file($realFile)) {
            return false;
        }

        return file_put_contents($realFile, $content) !== false;
    }

    public function findReadme(string $rootPath): ?string
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return null;
        }

        foreach (scandir($realRoot) ?: [] as $item) {
            if (strtolower($item) !== 'readme.md') {
                continue;
            }

            return $item;
        }

        return null;
    }

    public function createFile(string $rootPath, string $relativePath): bool
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return false;
        }

        $candidate = $this->resolveNewPath($realRoot, $relativePath);

        if ($candidate === null || file_exists($candidate)) {
            return false;
        }

        $directory = dirname($candidate);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($candidate, '') !== false;
    }

    public function deleteFile(string $rootPath, string $relativePath): bool
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return false;
        }

        $candidate = $realRoot.DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');
        $realFile = realpath($candidate);

        if ($realFile === false || ! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR)) {
            return false;
        }

        if (! is_file($realFile)) {
            return false;
        }

        return unlink($realFile);
    }

    public function createDirectory(string $rootPath, string $relativePath): bool
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return false;
        }

        $candidate = $this->resolveNewPath($realRoot, $relativePath);

        if ($candidate === null || file_exists($candidate)) {
            return false;
        }

        return mkdir($candidate, 0755, true);
    }

    public function moveNode(string $rootPath, string $fromPath, string $toPath): bool
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return false;
        }

        $fromCandidate = $realRoot.DIRECTORY_SEPARATOR.ltrim($fromPath, '/\\');
        $fromReal = realpath($fromCandidate);

        if ($fromReal === false || ! str_starts_with($fromReal, $realRoot.DIRECTORY_SEPARATOR)) {
            return false;
        }

        $toCandidate = $this->resolveNewPath($realRoot, $toPath);

        if ($toCandidate === null) {
            return false;
        }

        if ($toCandidate === $fromReal) {
            return true;
        }

        if (is_dir($fromReal) && str_starts_with($toCandidate, $fromReal.DIRECTORY_SEPARATOR)) {
            return false;
        }

        return rename($fromReal, $toCandidate);
    }

    /**
     * @param  string[]  $extensions
     * @return array<int, array{name: string, type: string, path: string, children?: array}>
     */
    private function buildTree(string $realRoot, string $currentPath, array $extensions): array
    {
        $folders = [];
        $files = [];

        foreach (new DirectoryIterator($currentPath) as $item) {
            if ($item->isDot() || str_starts_with($item->getFilename(), '.')) {
                continue;
            }

            $relativePath = ltrim(str_replace($realRoot, '', $item->getPathname()), DIRECTORY_SEPARATOR);

            if ($item->isDir()) {
                $children = $this->buildTree($realRoot, $item->getPathname(), $extensions);

                if (! empty($children)) {
                    $folders[] = [
                        'name' => $item->getFilename(),
                        'type' => 'folder',
                        'path' => $relativePath,
                        'children' => $children,
                    ];
                }
            } elseif ($item->isFile() && in_array(strtolower($item->getExtension()), $extensions, true)) {
                $files[] = [
                    'name' => $item->getFilename(),
                    'type' => 'file',
                    'path' => $relativePath,
                ];
            }
        }

        usort($folders, fn ($a, $b) => strcmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return array_merge($folders, $files);
    }

    private function realRoot(string $rootPath): ?string
    {
        $realRoot = realpath($rootPath);

        if ($realRoot === false || ! is_dir($realRoot)) {
            return null;
        }

        return $realRoot;
    }

    private function resolveNewPath(string $realRoot, string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');

        if ($relativePath === '') {
            return null;
        }

        $segments = [];

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return null;
        }

        return $realRoot.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
    }
}
