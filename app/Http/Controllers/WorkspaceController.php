<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaceService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'workspaces' => $this->workspaceService->allWithId(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:1000'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);

        $workspace = $this->workspaceService->create(
            $validated['name'],
            $validated['path'],
            $validated['slug'] ?? null,
        );

        return response()->json([
            'workspace' => [
                'id' => $workspace->id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
                'path' => $workspace->path,
            ],
        ], 201);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        $updated = $this->workspaceService->update(
            $workspace,
            $validated['name'],
            $validated['path'],
        );

        return response()->json([
            'workspace' => [
                'id' => $updated->id,
                'slug' => $updated->slug,
                'name' => $updated->name,
                'path' => $updated->path,
            ],
        ]);
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->workspaceService->delete($workspace);

        return response()->json(['deleted' => true]);
    }
}
