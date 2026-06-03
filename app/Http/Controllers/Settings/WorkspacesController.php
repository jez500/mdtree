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
        return Inertia::render('settings/Workspaces', [
            'workspaces' => $this->workspaceService->allWithId(),
        ]);
    }
}
