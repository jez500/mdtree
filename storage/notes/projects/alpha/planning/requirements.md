# Requirements — Project Alpha

> Part of [Project Alpha](../overview.md). See also [Roadmap](roadmap.md).

## Functional Requirements

### Must Have (v1)

1. Browse a folder tree of `.md` files in a sidebar
2. Click a file to render it as HTML
3. Support multiple workspaces switchable from the UI
4. No login required for single-user local deployments
5. Path traversal protection on all file reads

### Should Have (v2)

1. WYSIWYG editing (TipTap) that saves back to `.md`
2. Search across all files in the active workspace
3. Internal wiki-style links (`[[Page Name]]`)
4. Dark mode

### Nice to Have (v3)

1. Tag-based filtering
2. Graph view of document relationships
3. Image drag-and-drop upload

## Non-Functional Requirements

| Requirement | Target |
|---|---|
| Page load (cold) | < 500ms |
| File open (warm) | < 100ms |
| Max workspace size | 10,000 files |
| Browser support | Chrome, Firefox, Safari (latest 2 versions) |

## Constraints

- All notes must remain as plain `.md` files on disk (no database storage of content)
- Must work without an internet connection after initial asset load
- No external note-sync service dependency

---

*See: [Roadmap](roadmap.md) — [Architecture](decisions/architecture/backend.md)*
