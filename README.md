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

## Recommended Initial Approach

### Phase 1 — Skeleton

1. `php artisan make:model Workspace -m` — workspace table
2. Seed two example workspaces pointing to local directories
3. Workspace switcher in the nav (dropdown)
4. Active workspace stored in `session('workspace_id')`

### Phase 2 — File Tree

5. `FileTreeService` that walks the root path and returns a nested array
6. `TreeController@show` — returns the tree via Inertia prop
7. Sidebar Vue component renders the tree with collapsible folders
8. Click a file → navigate to `/editor?path=...`

### Phase 3 — Editor

9. `FileController@show` and `@update` — read/write Markdown
10. TipTap Vue component, initialised with the file's Markdown
11. Debounced auto-save (1 s after last keystroke) + `Cmd/Ctrl+S`
12. Unsaved changes indicator in the tab/title

### Phase 4 — Polish

13. Create / rename / delete file via right-click context menu on the tree
14. Breadcrumb trail above the editor
15. Keyboard shortcut to focus the search/filter input in the sidebar
16. Dark mode (Tailwind `dark:` variants)

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

## Open Questions / Decisions

| Question | Recommendation |
|---|---|
| Multi-user or single-user? | Start single-user; add auth later via Fortify |
| Workspace paths configured in DB or `.env`? | DB — easier to add/remove at runtime |
| Image handling? | Serve images as static files from the workspace root |
| Internal wiki links `[[...]]`? | TipTap custom extension; implement in Phase 4 |
| Search across all files? | Simple `grep` via `Process::run()` for now; full-text index later |
