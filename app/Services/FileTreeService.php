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
        $realRoot = realpath($rootPath);

        if ($realRoot === false) {
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
        $realRoot = realpath($rootPath);

        if ($realRoot === false) {
            return false;
        }

        $candidate = $realRoot.DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');

        // realpath() returns false for non-existent files; resolve manually for new files
        $realFile = realpath($candidate) ?: $candidate;

        if (! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR)) {
            return false;
        }

        if (! is_file($realFile)) {
            return false;
        }

        return file_put_contents($realFile, $content) !== false;
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
}
