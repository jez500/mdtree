# mdtree

A web-based Markdown knowledge-base browser and editor. Browse folder trees, edit rich text with a WYSIWYG editor, and switch between workspaces — all backed by plain `.md` files on disk.

---

## Features

- **Sidebar file tree** — recursive folder/file browser with drag-and-drop reordering
- **WYSIWYG editor** — TipTap-powered rich text editing that reads and writes plain Markdown
- **Markdown viewer** — rendered HTML view for read-only files
- **Workspace support** — named root directories with instant switching
- **Wiki-style internal links** — `[text](other-note.md)` resolves relative to the current file
- **Full-text search** — search across all files in a workspace
- **Image uploads** — drag-and-drop or paste images; stored as local assets
- **Tables** — create and edit tables via a bubble menu
- **File operations** — create, rename, move, and delete files and directories
- **Authentication** — login, registration, 2FA, and email verification via Laravel Fortify
- **Keyboard-friendly** — slash commands, formatting shortcuts, and fast navigation

---

## Tech Stack

| Layer     | Choice              | Purpose                                   |
|-----------|---------------------|-------------------------------------------|
| Backend   | Laravel 13          | Routing, workspace config, file I/O       |
| Frontend  | Vue 3 + Inertia v3  | SPA feel without a separate API           |
| Editor    | TipTap (novel-vue)  | ProseMirror-based WYSIWYG with Markdown   |
| Styling   | Tailwind CSS v4     | Utility-first styling                     |
| Auth      | Laravel Fortify     | Login, registration, 2FA                  |
| Testing   | Pest v4             | Expressive PHP test framework             |

---

## Requirements

- PHP 8.3+
- Node 18+
- SQLite (default) or any Laravel-supported database

---

## Getting Started

### 1. Clone and install

```bash
git clone https://github.com/your-org/mdtree.git
cd mdtree
composer setup
```

The `composer setup` command installs dependencies, copies `.env.example`, generates an app key, runs migrations, and builds frontend assets.

### 2. Configure a workspace

Edit `.env` to point a workspace at a directory of Markdown files:

```env
MDTREE_DEFAULT_NAME="My Notes"
MDTREE_DEFAULT_PATH=/home/you/notes
```

You can add more workspaces in `config/mdtree.php`. Each key becomes a URL slug (`/browser/{key}`).

### 3. Run the dev server

```bash
composer run dev
```

This starts the Laravel server, queue worker, log viewer, and Vite dev server concurrently. Open `http://localhost:8000` and register an account.

---

## Docker

Build and run with Docker:

```bash
docker build -t mdtree .
docker run --rm -p 8000:80 \
  -v ./storage:/var/www/html/storage \
  mdtree
```

On first boot the container copies `.env.example`, generates an app key, and runs migrations automatically. The SQLite database lives at `storage/database.sqlite` — mount a volume to persist it.

---

## Usage

### Browsing files

The sidebar shows the file tree for the active workspace. Click any file to open it. Directories are sorted first, then files, both alphabetically. The tree respects the file extensions configured in `MDTREE_EXTENSIONS` (default: `md,txt`).

### Editing

Click a file to open it in the WYSIWYG editor. Changes auto-save (debounced). You can also toggle to raw Markdown mode via the toolbar.

**Formatting shortcuts:**

| Shortcut          | Action            |
|-------------------|-------------------|
| `Ctrl/Cmd + B`    | Bold              |
| `Ctrl/Cmd + I`    | Italic            |
| `Ctrl/Cmd + E`    | Inline code       |
| `Ctrl/Cmd + K`    | Insert/edit link  |
| `/`               | Open slash menu   |

### Internal links

Link to other files using relative paths:

```markdown
[Related note](../folder/other-note.md)
```

Internal links navigate in-page without opening a new tab. External URLs (`http://`/`https://`) open in a new tab as expected.

### Images

Paste or drag-and-drop images into the editor. They are stored in an `assets/` folder within the workspace and referenced with relative paths.

### Search

Use the search button in the header (or keyboard shortcut) to search across all files in the current workspace.

### File operations

Right-click or use the toolbar to create new files and directories, rename, move (drag-and-drop in the sidebar), or delete items.

---

## Configuration

All mdtree-specific config lives in `config/mdtree.php`:

```php
return [
    'workspaces' => [
        'default' => [
            'name' => env('MDTREE_DEFAULT_NAME', 'My Notes'),
            'path' => env('MDTREE_DEFAULT_PATH', storage_path('notes')),
        ],
    ],

    'extensions' => explode(',', env('MDTREE_EXTENSIONS', 'md,txt')),
];
```

| Env Variable          | Default               | Description                        |
|-----------------------|-----------------------|------------------------------------|
| `MDTREE_DEFAULT_NAME` | `My Notes`            | Display name for the default workspace |
| `MDTREE_DEFAULT_PATH` | `storage/notes`       | Filesystem path for the default workspace |
| `MDTREE_EXTENSIONS`   | `md,txt`              | Comma-separated list of shown file extensions |

---

## Security

- **Path traversal** — all user-supplied paths are resolved with `realpath()` and confirmed inside the workspace root before any read or write.
- **Workspace isolation** — only configured workspaces are accessible; users cannot browse outside their roots.
- **File type restriction** — only files with allowed extensions appear in the tree and are served by the API.
- **Authentication** — all routes are protected by Laravel Fortify's auth middleware.

---

## Development

### Commands

```bash
composer run dev          # Start all dev servers
composer run test         # Run tests (with lint check)
composer run lint         # Auto-fix PHP style
npm run lint              # Auto-fix JS/Vue style
npm run format            # Format JS/Vue with Prettier
npm run types:check       # TypeScript type check
npm run build             # Production frontend build
```

### Running tests

```bash
php artisan test                    # All tests
php artisan test --compact          # Compact output
php artisan test --filter=testName  # Single test
```

---

## Troubleshooting

- **Read-only database errors** — ensure `storage/database.sqlite` and the `storage/` directory are writable by the web server user.
- **Frontend changes not reflected** — run `npm run build` (or `npm run dev` for hot reload).
- **Workspace not found** — check that the path in `MDTREE_DEFAULT_PATH` exists and is readable.
