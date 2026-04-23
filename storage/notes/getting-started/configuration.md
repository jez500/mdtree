# Configuration

> Part of the [Getting Started](first-steps.md) series. See also [Installation](installation.md).

## Workspace Configuration

Workspaces are defined in `config/mdtree.php`:

```php
return [
    'workspaces' => [
        'personal' => [
            'name' => 'Personal Notes',
            'path' => env('MDTREE_PERSONAL_PATH', '/home/user/notes'),
        ],
        'work' => [
            'name' => 'Work Notes',
            'path' => env('MDTREE_WORK_PATH', '/home/user/work'),
        ],
    ],
    'extensions' => ['md', 'txt'],
];
```

Each key in the `workspaces` array becomes a URL slug: `/browser/personal`, `/browser/work`.

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `MDTREE_DEFAULT_NAME` | `My Notes` | Display name for the default workspace |
| `MDTREE_DEFAULT_PATH` | `storage/notes` | Absolute path to the notes folder |
| `MDTREE_EXTENSIONS` | `md,txt` | Comma-separated list of allowed extensions |

## Multiple Workspaces

Add extra entries directly in `config/mdtree.php`. There is no limit. Each workspace is independently navigable and the sidebar workspace switcher will list all of them.

## File Extensions

Only files with extensions listed in `mdtree.extensions` appear in the sidebar tree. To include plain text files:

```dotenv
MDTREE_EXTENSIONS=md,txt,markdown
```

---

*Back to: [Installation](installation.md) — [First Steps](first-steps.md)*
