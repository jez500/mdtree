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
}
