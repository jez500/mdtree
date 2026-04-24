<?php

namespace App\Http\Controllers;

use App\Services\FileTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
