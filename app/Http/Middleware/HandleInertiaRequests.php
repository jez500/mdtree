<?php

namespace App\Http\Middleware;

use App\Services\FileTreeService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly FileTreeService $fileTreeService,
        private readonly WorkspaceService $workspaceService,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            ...$this->browserProps($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function browserProps(Request $request): array
    {
        if ($request->user() === null) {
            return [];
        }

        $workspaces = $this->workspaceService->all();
        $workspace = $request->route('workspace') ?? array_key_first($workspaces);

        if (! is_string($workspace) || ! isset($workspaces[$workspace])) {
            $workspace = array_key_first($workspaces);
        }

        return [
            'workspace' => $workspace,
            'workspaces' => $workspaces,
            'tree' => fn () => $this->fileTreeService->cachedTree(
                $workspaces[$workspace]['path'],
                config('mdtree.extensions'),
            ),
            'filePath' => null,
        ];
    }
}
