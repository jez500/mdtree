<?php

use App\Models\User;

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
