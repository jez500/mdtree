<?php

namespace App\Http\Controllers;

use App\Services\FileTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class BrowserController extends Controller
{
    public function __construct(private readonly FileTreeService $fileTreeService) {}

    public function index(): RedirectResponse
    {
        $firstWorkspace = array_key_first(config('mdtree.workspaces'));

        return redirect()->route('browser.show', ['workspace' => $firstWorkspace]);
    }

    public function show(string $workspace, Request $request): Response
    {
        $workspaces = config('mdtree.workspaces');

        abort_unless(isset($workspaces[$workspace]), 404);

        $workspaceConfig = $workspaces[$workspace];
        $extensions = config('mdtree.extensions');

        $tree = $this->fileTreeService->tree($workspaceConfig['path'], $extensions);

        $filePath = $request->query('path');

        if ($filePath === null) {
            $filePath = $this->fileTreeService->findReadme($workspaceConfig['path']);
        }

        $fileContent = null;

        if ($filePath !== null) {
            $fileContent = $this->fileTreeService->readFile($workspaceConfig['path'], $filePath);
            abort_if($fileContent === null, 404);
        }

        return Inertia::render('Browser', [
            'workspace' => $workspace,
            'workspaces' => $workspaces,
            'tree' => $tree,
            'filePath' => $filePath,
            'fileContent' => $fileContent,
        ]);
    }

    public function search(string $workspace, Request $request): JsonResponse
    {
        $workspaces = config('mdtree.workspaces');

        abort_unless(isset($workspaces[$workspace]), 404);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'results' => $this->fileTreeService->search(
                $workspaces[$workspace]['path'],
                config('mdtree.extensions'),
                $validated['q'] ?? '',
            ),
        ]);
    }

    public function resolveLink(string $workspace, Request $request): RedirectResponse
    {
        $workspaces = config('mdtree.workspaces');

        abort_unless(isset($workspaces[$workspace]), 404);

        $validated = $request->validate([
            'from' => ['required', 'string'],
            'href' => ['required', 'string'],
        ]);

        $targetPath = $this->fileTreeService->resolveMarkdownLink(
            $workspaces[$workspace]['path'],
            $validated['from'],
            $validated['href'],
        );

        abort_if($targetPath === null, 404);

        return Redirect::route('browser.show', [
            'workspace' => $workspace,
            'path' => $targetPath,
        ]);
    }
}
