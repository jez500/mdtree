<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WorkspaceService
{
    public function __construct(private readonly FileTreeService $fileTreeService) {}

    /**
     * @return array<string, array{name: string, path: string}>
     */
    public function all(): array
    {
        return Cache::remember('mdtree.workspaces', now()->addMinutes(5), function () {
            $dbWorkspaces = Workspace::all()->keyBy('slug')->map(fn ($ws) => [
                'name' => $ws->name,
                'path' => $ws->path,
            ])->toArray();

            $configWorkspaces = config('mdtree.workspaces', []);

            foreach ($configWorkspaces as $slug => $config) {
                if (! isset($dbWorkspaces[$slug])) {
                    $dbWorkspaces[$slug] = [
                        'name' => $config['name'] ?? Str::title($slug),
                        'path' => $config['path'] ?? '',
                    ];
                }
            }

            return $dbWorkspaces;
        });
    }

    /**
     * @return array<int, array{id: int, slug: string, name: string, path: string}>
     */
    public function allWithId(): array
    {
        return Workspace::all()->map(fn ($ws) => [
            'id' => $ws->id,
            'slug' => $ws->slug,
            'name' => $ws->name,
            'path' => $ws->path,
        ])->toArray();
    }

    public function find(string $slug): ?array
    {
        $workspaces = $this->all();

        return $workspaces[$slug] ?? null;
    }

    public function findById(int $id): ?Workspace
    {
        return Workspace::find($id);
    }

    public function create(string $name, string $path, ?string $slug = null): Workspace
    {
        $slug = $slug ?? Str::slug($name);

        $counter = '';
        $originalSlug = $slug;

        while (Workspace::where('slug', $slug.$counter)->exists()) {
            $counter = ($counter ?? 0) + 1;
            $slug = $originalSlug.'-'.$counter;
        }

        $workspace = Workspace::create([
            'slug' => $slug,
            'name' => $name,
            'path' => $path,
        ]);

        $this->flushCache();

        return $workspace;
    }

    public function update(Workspace $workspace, string $name, string $path): Workspace
    {
        $workspace->update([
            'name' => $name,
            'path' => $path,
        ]);

        $this->flushCache();

        return $workspace->fresh();
    }

    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
        $this->flushCache();
    }

    public function flushCache(): void
    {
        Cache::forget('mdtree.workspaces');
        Cache::forget('mdtree.workspaces.all');
    }

    public function seedDefaults(): void
    {
        $configWorkspaces = config('mdtree.workspaces', []);

        foreach ($configWorkspaces as $slug => $config) {
            if (! Workspace::where('slug', $slug)->exists()) {
                Workspace::create([
                    'slug' => $slug,
                    'name' => $config['name'] ?? Str::title($slug),
                    'path' => $config['path'] ?? '',
                ]);
            }
        }

        $this->flushCache();
    }
}
