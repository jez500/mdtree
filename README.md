# mdtree

A web-based Markdown knowledge base browser and editor — Obsidian in a browser. Browse folder trees, edit rich text, and switch between workspaces, all backed by plain `.md` files on disk.

---

## Features

- **Sidebar file tree** — recursive folder/file browser for `.md` files
- **WYSIWYG editor** — TipTap-powered rich text editing that reads/writes Markdown
- **Workspaces** — named root directories; switch between them instantly
- **Live rendering** — Markdown rendered as HTML by default, editable in place
- **Keyboard-friendly** — designed for fast navigation

---

## Tech Stack

| Layer | Choice | Rationale |
|---|---|---|
| Backend | Laravel 13 | Routing, workspace config, file I/O |
| Frontend | Vue 3 + Inertia v3 | SPA feel without API overhead |
| Editor | TipTap | Best-in-class ProseMirror wrapper with Markdown extensions |
| Styling | Tailwind CSS v4 | Utility-first, fast iteration |
| Testing | Pest v4 | Expressive PHP tests |

---

## Architecture Overview

### Workspaces

A workspace is a named pointer to a root directory on the server filesystem. Stored in the database (or a config file for single-user setups). The active workspace is stored in the session.

```
workspaces table:
  id, name, root_path, created_at, updated_at
```

### File Tree

The backend walks the workspace root using PHP's `RecursiveDirectoryIterator`, returning a JSON tree of folders and `.md` files. Directories come first, sorted alphabetically. Hidden files/folders (dotfiles) are excluded.

```
GET /api/tree          → full tree for active workspace
GET /api/tree?path=foo → subtree (for lazy-loading deep trees)
```

### File Read/Write

Files are read and written directly from/to disk. The editor sends Markdown (converted from TipTap's internal doc by `@tiptap/extension-markdown`), and the server writes it atomically.

```
GET  /api/files?path=notes/foo.md   → returns raw Markdown
PUT  /api/files?path=notes/foo.md   → saves raw Markdown body
POST /api/files                     → create new file
DELETE /api/files?path=...          → delete file
```

All paths are resolved relative to the active workspace root and are validated to prevent path traversal (no `../` escapes).

### Editor (TipTap)

TipTap renders the Markdown as a rich ProseMirror document. The key extension is `@tiptap/extension-markdown` which handles bidirectional conversion. The editor auto-saves on blur (debounced) and on an explicit save shortcut.

Extensions to include from the start:
- `StarterKit` (headings, bold, italic, lists, blockquote, code, hr)
- `@tiptap/extension-markdown`
- `@tiptap/extension-link`
- `@tiptap/extension-image`
- `@tiptap/extension-table`
- `@tiptap/extension-code-block-lowlight` (syntax highlighting via lowlight)
- `@tiptap/extension-task-list` + `@tiptap/extension-task-item` (GFM checkboxes)

---


## Security Considerations

- **Path traversal** — all user-supplied paths must be resolved with `realpath()` and confirmed to be inside the workspace root before any read or write
- **Workspace isolation** — users can only access workspaces assigned to them (if multi-user)
- **File type restriction** — only `.md` files are served via the API; directory listings exclude everything else

---

## Key Packages to Install

```bash
# PHP
# (no extra Composer packages needed beyond the Laravel defaults)

# Node
npm install @tiptap/vue-3 @tiptap/starter-kit \
  @tiptap/extension-markdown \
  @tiptap/extension-link \
  @tiptap/extension-image \
  @tiptap/extension-table \
  @tiptap/extension-code-block-lowlight \
  @tiptap/extension-task-list \
  @tiptap/extension-task-item \
  lowlight
```

---

## Docker

Build the image with your requested tag:

```bash
docker build -t jez500/mdtree .
```

Run the container:

```bash
docker run --rm -p 8000:80 --name mdtree -v ./storage:/var/www/html/storage jez500/mdtree
```

Open the app at `http://localhost:8000`.

Notes:

- On first boot, the container copies `.env.example` to `.env`, generates `APP_KEY`, and runs migrations automatically.
- Data is stored in `database/database.sqlite` inside the container. Mount a volume if you want persistence across container recreations.

## Troubleshooting

* Read only db errors: Ensure `storage/database.sqlite` and `storage` are writable by the web server user.
