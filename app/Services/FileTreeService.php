<?php

namespace App\Services;

use DirectoryIterator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

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
        return $this->cachedTree($rootPath, $extensions);
    }

    /**
     * Build and cache a recursive file tree for a workspace root.
     *
     * @param  string[]  $extensions
     * @return array<int, array{name: string, type: string, path: string, children?: array}>
     */
    public function cachedTree(string $rootPath, array $extensions): array
    {
        $realRoot = realpath($rootPath);

        if ($realRoot === false || ! is_dir($realRoot)) {
            return [];
        }

        return Cache::remember(
            $this->treeCacheKey($realRoot, $extensions),
            now()->addMinutes(5),
            fn () => $this->buildTree($realRoot, $realRoot, $extensions),
        );
    }

    /**
     * Forget a cached tree for a workspace root.
     *
     * @param  string[]  $extensions
     */
    public function forgetTreeCache(string $rootPath, array $extensions): void
    {
        $realRoot = realpath($rootPath);

        if ($realRoot === false || ! is_dir($realRoot)) {
            return;
        }

        Cache::forget($this->treeCacheKey($realRoot, $extensions));
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

    /**
     * @param  string[]  $extensions
     * @return array<int, array{title: string, path: string, excerpt: string}>
     */
    public function search(string $rootPath, array $extensions, string $query, int $limit = 20): array
    {
        $realRoot = $this->realRoot($rootPath);
        $query = trim($query);

        if ($realRoot === null || $query === '') {
            return [];
        }

        $matches = [];

        foreach ($this->searchableFiles($realRoot, $extensions) as $file) {
            $content = file_get_contents($file->getPathname());

            if ($content === false) {
                continue;
            }

            $relativePath = $this->relativeFilePath($realRoot, $file);
            $title = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $titleMatches = substr_count(strtolower($title), strtolower($query));
            $pathMatches = substr_count(strtolower($relativePath), strtolower($query));
            $contentMatches = substr_count(strtolower($content), strtolower($query));

            if ($titleMatches + $pathMatches + $contentMatches === 0) {
                continue;
            }

            $matches[] = [
                'title' => $title,
                'path' => $relativePath,
                'excerpt' => $this->excerpt($content, $query),
                'score' => ($titleMatches * 1000) + ($pathMatches * 100) + $contentMatches,
            ];
        }

        usort($matches, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['path'], $b['path']));

        return array_map(
            fn (array $match): array => [
                'title' => $match['title'],
                'path' => $match['path'],
                'excerpt' => $match['excerpt'],
            ],
            array_slice($matches, 0, $limit),
        );
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

    public function storeImageAsset(string $rootPath, UploadedFile $image): ?string
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return null;
        }

        $assetsDirectory = $realRoot.DIRECTORY_SEPARATOR.'assets';

        if (! is_dir($assetsDirectory) && ! mkdir($assetsDirectory, 0755, true) && ! is_dir($assetsDirectory)) {
            return null;
        }

        $filename = $this->imageAssetName($image);
        $image->move($assetsDirectory, $filename);

        return 'assets/'.$filename;
    }

    public function assetPath(string $rootPath, string $relativePath): ?string
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null) {
            return null;
        }

        $candidate = $realRoot.DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');
        $realFile = realpath($candidate);
        $assetsRoot = $realRoot.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR;

        if ($realFile === false || ! str_starts_with($realFile, $assetsRoot)) {
            return null;
        }

        return is_file($realFile) ? $realFile : null;
    }

    public function relativePathFromFile(string $fromPath, string $targetPath): string
    {
        return $this->relativePath(dirname($this->normalizePath($fromPath)), $this->normalizePath($targetPath));
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

        if (! rename($fromReal, $toCandidate)) {
            return false;
        }

        $this->rewriteMovedMarkdownLinks($realRoot, $fromPath, $toPath);

        return true;
    }

    public function resolveMarkdownLink(string $rootPath, string $fromPath, string $href): ?string
    {
        $realRoot = $this->realRoot($rootPath);

        if ($realRoot === null || ! $this->isLocalMarkdownHref($href)) {
            return null;
        }

        $parts = $this->splitHref($href);
        $resolved = $this->normalizeRelativePath(dirname($this->normalizePath($fromPath)), $parts['path']);

        if ($resolved === null || ! $this->isMarkdownPath($resolved)) {
            return null;
        }

        $realFile = realpath($realRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $resolved));

        if ($realFile === false || ! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR) || ! is_file($realFile)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param  string[]  $extensions
     * @return array<int, array{name: string, type: string, path: string, children?: array}>
     */
    private function buildTree(string $realRoot, string $currentPath, array $extensions): array
    {
        $folders = [];
        $files = [];

        if (! is_readable($currentPath)) {
            return [];
        }

        try {
            $iterator = new DirectoryIterator($currentPath);
        } catch (UnexpectedValueException $e) {
            return [];
        }

        foreach ($iterator as $item) {
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

    /**
     * @param  string[]  $extensions
     */
    private function treeCacheKey(string $realRoot, array $extensions): string
    {
        $normalizedExtensions = array_values(array_filter(
            array_map(
                static fn (string $extension): string => strtolower(trim($extension)),
                $extensions,
            ),
            static fn (string $extension): bool => $extension !== '',
        ));

        sort($normalizedExtensions);

        return 'mdtree.tree.'.sha1($realRoot.'|'.implode(',', $normalizedExtensions));
    }

    private function imageAssetName(UploadedFile $image): string
    {
        $name = Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $extension = strtolower($image->extension() ?: $image->getClientOriginalExtension());

        return $name.'-'.Str::lower(Str::random(10)).'.'.$extension;
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

    private function rewriteMovedMarkdownLinks(string $realRoot, string $fromPath, string $toPath): void
    {
        $fromPath = $this->normalizePath($fromPath);
        $toPath = $this->normalizePath($toPath);

        foreach ($this->markdownFiles($realRoot) as $currentPath) {
            $absolutePath = $realRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $currentPath);
            $content = file_get_contents($absolutePath);

            if ($content === false) {
                continue;
            }

            $oldPath = $this->pathBeforeMove($currentPath, $fromPath, $toPath);
            $updated = $this->rewriteMarkdownLinks($content, $oldPath, $currentPath, $fromPath, $toPath);

            if ($updated !== $content) {
                file_put_contents($absolutePath, $updated);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function markdownFiles(string $realRoot): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            flags: RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! $this->isMarkdownPath($file->getFilename())) {
                continue;
            }

            $files[] = ltrim(str_replace([$realRoot, DIRECTORY_SEPARATOR], ['', '/'], $file->getPathname()), '/');
        }

        return $files;
    }

    /**
     * @param  string[]  $extensions
     * @return array<int, SplFileInfo>
     */
    private function searchableFiles(string $realRoot, array $extensions): array
    {
        $files = [];

        if (! is_readable($realRoot)) {
            return [];
        }

        try {
            $iterator = new DirectoryIterator($realRoot);
        } catch (UnexpectedValueException $e) {
            return [];
        }

        foreach ($iterator as $item) {
            if ($item->isDot() || str_starts_with($item->getFilename(), '.')) {
                continue;
            }

            if ($item->isDir()) {
                array_push($files, ...$this->searchableFiles($item->getPathname(), $extensions));

                continue;
            }

            if ($item->isFile() && in_array(strtolower($item->getExtension()), $extensions, true)) {
                $files[] = new SplFileInfo($item->getPathname());
            }
        }

        return $files;
    }

    private function relativeFilePath(string $realRoot, SplFileInfo $file): string
    {
        return ltrim(str_replace([$realRoot, DIRECTORY_SEPARATOR], ['', '/'], $file->getPathname()), '/');
    }

    private function excerpt(string $content, string $query): string
    {
        $position = stripos($content, $query);
        $normalized = trim(preg_replace('/\s+/', ' ', $content) ?? $content);

        if ($position === false) {
            return mb_substr($normalized, 0, 160);
        }

        $start = max(0, $position - 60);
        $excerpt = trim(preg_replace('/\s+/', ' ', mb_substr($content, $start, 180)) ?? '');

        return ($start > 0 ? '...' : '').$excerpt;
    }

    private function rewriteMarkdownLinks(
        string $content,
        string $oldFilePath,
        string $currentFilePath,
        string $fromPath,
        string $toPath,
    ): string {
        return preg_replace_callback(
            '/(?<!!)\[[^\]\n]+\]\((?<href>[^)\s]+(?:\s+"[^"]*")?)\)/',
            function (array $matches) use ($oldFilePath, $currentFilePath, $fromPath, $toPath): string {
                $hrefWithTitle = $matches['href'];
                [$href, $title] = $this->splitMarkdownHrefAndTitle($hrefWithTitle);

                if (! $this->isLocalMarkdownHref($href)) {
                    return $matches[0];
                }

                $parts = $this->splitHref($href);
                $oldTargetPath = $this->normalizeRelativePath(dirname($oldFilePath), $parts['path']);

                if ($oldTargetPath === null || ! $this->isMarkdownPath($oldTargetPath)) {
                    return $matches[0];
                }

                $newTargetPath = $this->pathAfterMove($oldTargetPath, $fromPath, $toPath);

                if ($newTargetPath === $oldTargetPath && $oldFilePath === $currentFilePath) {
                    return $matches[0];
                }

                $nextHref = $this->relativePath(dirname($currentFilePath), $newTargetPath).$parts['query'].$parts['fragment'];

                return str_replace($hrefWithTitle, $nextHref.$title, $matches[0]);
            },
            $content,
        ) ?? $content;
    }

    private function pathBeforeMove(string $currentPath, string $fromPath, string $toPath): string
    {
        if ($currentPath === $toPath) {
            return $fromPath;
        }

        $prefix = $toPath.'/';

        if (str_starts_with($currentPath, $prefix)) {
            return $fromPath.'/'.substr($currentPath, strlen($prefix));
        }

        return $currentPath;
    }

    private function pathAfterMove(string $path, string $fromPath, string $toPath): string
    {
        if ($path === $fromPath) {
            return $toPath;
        }

        $prefix = $fromPath.'/';

        if (str_starts_with($path, $prefix)) {
            return $toPath.'/'.substr($path, strlen($prefix));
        }

        return $path;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitMarkdownHrefAndTitle(string $href): array
    {
        if (preg_match('/^(?<href>\S+)(?<title>\s+"[^"]*")$/', $href, $matches) === 1) {
            return [$matches['href'], $matches['title']];
        }

        return [$href, ''];
    }

    /**
     * @return array{path: string, query: string, fragment: string}
     */
    private function splitHref(string $href): array
    {
        $fragment = '';
        $query = '';
        $path = $href;

        if (($fragmentPosition = strpos($path, '#')) !== false) {
            $fragment = substr($path, $fragmentPosition);
            $path = substr($path, 0, $fragmentPosition);
        }

        if (($queryPosition = strpos($path, '?')) !== false) {
            $query = substr($path, $queryPosition);
            $path = substr($path, 0, $queryPosition);
        }

        return [
            'path' => rawurldecode($path),
            'query' => $query,
            'fragment' => $fragment,
        ];
    }

    private function isLocalMarkdownHref(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return false;
        }

        return parse_url($href, PHP_URL_SCHEME) === null;
    }

    private function isMarkdownPath(string $path): bool
    {
        return str_ends_with(strtolower(parse_url($path, PHP_URL_PATH) ?? $path), '.md');
    }

    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function normalizeRelativePath(string $fromDirectory, string $path): ?string
    {
        $combined = trim($fromDirectory, '/');

        if ($combined !== '') {
            $combined .= '/';
        }

        $segments = [];

        foreach (explode('/', $combined.$this->normalizePath($path)) as $segment) {
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

        return implode('/', $segments);
    }

    private function relativePath(string $fromDirectory, string $targetPath): string
    {
        $fromSegments = $fromDirectory === '.' ? [] : array_values(array_filter(explode('/', trim($fromDirectory, '/'))));
        $targetSegments = array_values(array_filter(explode('/', trim($targetPath, '/'))));

        while ($fromSegments !== [] && $targetSegments !== [] && $fromSegments[0] === $targetSegments[0]) {
            array_shift($fromSegments);
            array_shift($targetSegments);
        }

        return implode('/', array_merge(array_fill(0, count($fromSegments), '..'), $targetSegments));
    }
}
