# Architecture Decision — Backend

> Path: `projects/alpha/planning/decisions/architecture/backend.md`  
> This is a **level 5** nested file — used to test deep tree rendering.  
> Parent: [Architecture decisions](.) — [Planning](../../requirements.md) — [Alpha](../../../overview.md)

## Decision

Use Laravel as a thin file-serving backend. No Eloquent models or database tables for note content. All notes live as plain files on disk.

## Context

We evaluated three approaches:

| Approach | Pros | Cons |
|---|---|---|
| **Laravel + filesystem** | Simple, portable, notes stay as files | No real-time collab |
| Laravel + database (store MD in DB) | Faster search, history | Breaks plain-file portability |
| Static site (no backend) | Ultra simple | No workspace switching, no server-side path security |

## Rationale

The core value proposition of mdtree is **your files stay your files**. Storing Markdown in a database would break the ability to edit notes in any text editor, use Git to version them, or sync with tools like Syncthing or iCloud.

Laravel adds value as:
- A secure file access layer (path traversal protection)
- A workspace configuration store (`config/mdtree.php`)
- An Inertia server for the SPA shell

## Consequences

- No full-text search without a separate index (acceptable for v1, add SQLite FTS in v3)
- File renames must go through the API to keep URLs consistent
- No conflict resolution for concurrent edits (single-user tool, acceptable)

## Related

- [Requirements](../../requirements.md)
- [Roadmap](../../roadmap.md)
- [Root readme](../../../../../readme.md)
