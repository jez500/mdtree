<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspaces
    |--------------------------------------------------------------------------
    |
    | Each key becomes the URL slug: /browser/{key}
    | Add further workspaces directly in this array.
    |
    */

    'workspaces' => [
        'default' => [
            'name' => env('MDTREE_DEFAULT_NAME', 'My Notes'),
            'path' => env('MDTREE_DEFAULT_PATH', storage_path('notes')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | Only files with these extensions will appear in the tree.
    |
    */

    'extensions' => explode(',', env('MDTREE_EXTENSIONS', 'md,txt')),

];
