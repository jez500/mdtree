# Roadmap — Project Alpha

> See also: [Requirements](requirements.md) — [Overview](../overview.md)

## Milestones

### v1 — Viewer (April 2025)

Goal: A usable read-only browser.

- [x] Scaffold Laravel + Vue + Inertia
- [x] shadcn-vue sidebar with file tree
- [x] Workspace config via `config/mdtree.php`
- [x] `FileTreeService` with path traversal protection
- [x] `marked` + `DOMPurify` Markdown renderer
- [x] Recursive collapsible folder tree
- [ ] Fix remaining frontend bugs
- [ ] Polish styles

### v2 — Editor (June 2025)

Goal: Full WYSIWYG editing.

- [ ] TipTap integration
- [ ] `@tiptap/extension-markdown` bidirectional conversion
- [ ] Auto-save on blur (debounced 1s)
- [ ] `Cmd/Ctrl+S` explicit save
- [ ] Unsaved changes indicator
- [ ] File create / rename / delete

### v3 — Search & Links (Q3 2025)

Goal: Make it feel like Obsidian.

- [ ] Full-text search via `grep` or SQLite FTS
- [ ] `[[Wiki link]]` syntax support
- [ ] Backlinks panel
- [ ] Graph view

## Current Sprint

Working on v1 polish. Known issues:

1. `computed` import missing in some Vue components (fixed 2025-04-23)
2. Prose CSS needs table and blockquote refinement
3. Sidebar scroll position not preserved on navigation

---

*Updated: 2025-04-23*
