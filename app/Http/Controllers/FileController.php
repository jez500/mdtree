<?php

namespace App\Http\Controllers;

use App\Services\FileTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(private readonly FileTreeService $fileTreeService) {}

    public function save(string $workspace, Request $request): JsonResponse
    {
        $workspaces = config('mdtree.workspaces');

        abort_unless(isset($workspaces[$workspace]), 404);

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present', 'string'],
        ]);

        $workspaceConfig = $workspaces[$workspace];

        $saved = $this->fileTreeService->writeFile(
            $workspaceConfig['path'],
            $validated['path'],
            $validated['content'],
        );

        abort_unless($saved, 422, 'Failed to save file.');

        return response()->json(['saved' => true]);
    }

    public function createFile(string $workspace, Request $request): JsonResponse
    {
        $workspaceConfig = $this->workspaceConfig($workspace);

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $created = $this->fileTreeService->createFile($workspaceConfig['path'], $validated['path']);

        abort_unless($created, 422, 'Failed to create file.');

        return response()->json(['created' => true]);
    }

    public function deleteFile(string $workspace, Request $request): JsonResponse
    {
        $workspaceConfig = $this->workspaceConfig($workspace);

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $deleted = $this->fileTreeService->deleteFile($workspaceConfig['path'], $validated['path']);

        abort_unless($deleted, 422, 'Failed to delete file.');

        return response()->json(['deleted' => true]);
    }

    public function createDirectory(string $workspace, Request $request): JsonResponse
    {
        $workspaceConfig = $this->workspaceConfig($workspace);

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $created = $this->fileTreeService->createDirectory($workspaceConfig['path'], $validated['path']);

        abort_unless($created, 422, 'Failed to create directory.');

        return response()->json(['created' => true]);
    }

    public function moveNode(string $workspace, Request $request): JsonResponse
    {
        $workspaceConfig = $this->workspaceConfig($workspace);

        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);

        $moved = $this->fileTreeService->moveNode($workspaceConfig['path'], $validated['from'], $validated['to']);

        abort_unless($moved, 422, 'Failed to move node.');

        return response()->json(['moved' => true]);
    }

    /**
     * @return array{name: string, path: string}
     */
    private function workspaceConfig(string $workspace): array
    {
        $workspaces = config('mdtree.workspaces');

        abort_unless(isset($workspaces[$workspace]), 404);

        return $workspaces[$workspace];
    }
}
