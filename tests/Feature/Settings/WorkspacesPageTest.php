<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Config;

test('the root document renders a csrf-token meta tag for client requests', function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create());

    // A non-Inertia GET returns the full HTML document (app.blade.php). The
    // settings pages POST via fetch using the csrf-token meta tag, so it must
    // be present or those requests fail with a 419 CSRF token mismatch.
    $this->get(route('workspaces.edit'))
        ->assertOk()
        ->assertSee('name="csrf-token"', false);
});

test('the workspaces page does not clobber the shared workspaces map used by the switcher', function () {
    $this->withoutVite();
    Config::set('mdtree.workspaces', []);
    $this->actingAs(User::factory()->create());

    Workspace::create(['slug' => 'docs', 'name' => 'Docs', 'path' => sys_get_temp_dir()]);

    // The page-level array prop must be 'workspaceList'; the shared 'workspaces'
    // prop must stay a slug-keyed map so WorkspaceSwitcher links resolve to
    // /browser/{slug} instead of /browser/{index}.
    $this->get(route('workspaces.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Workspaces')
            ->has('workspaceList', 1)
            ->where('workspaceList.0.slug', 'docs')
            ->where('workspaces.docs.name', 'Docs')
        );
});
