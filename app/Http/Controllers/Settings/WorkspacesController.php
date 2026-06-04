<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceService;
use Inertia\Inertia;
use Inertia\Response;

class WorkspacesController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaceService) {}

    public function edit(): Response
    {
        // Keyed as 'workspaceList' (not 'workspaces') so this array prop does not
        // override the shared, slug-keyed 'workspaces' map used by the WorkspaceSwitcher.
        return Inertia::render('settings/Workspaces', [
            'workspaceList' => $this->workspaceService->allWithId(),
        ]);
    }
}
