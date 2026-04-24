# January 2025

> Journal — [← December 2024](../2024/december.md)

## Goals for January

- [ ] Ship mdtree v1 (viewer)
- [ ] Set up two real workspaces (personal + work)
- [ ] Write at least 20 notes to populate the tree
- [ ] Decide on TipTap editor approach for v2

## 2025-01-06 — Back from break

Started the year with a focus session on the frontend. The sidebar layout is working well. Key remaining issue: prose CSS needs work — tables and blockquotes look rough.

**Links to related docs:**
- [Project Alpha Roadmap](../../projects/alpha/planning/roadmap.md)
- [Requirements](../../projects/alpha/planning/requirements.md)

## 2025-01-14 — TipTap spike

Spent an afternoon with TipTap + `@tiptap/extension-markdown`. The bidirectional conversion is solid for standard Markdown. Caveats:

1. Tables convert cleanly
2. Task lists work via `@tiptap/extension-task-list`
3. Definition lists are NOT supported — would need a custom extension
4. Front matter YAML is ignored (stripped on save)

For v1 we're read-only so this doesn't matter yet.

## 2025-01-20 — First working version

The viewer is working end to end:

```
GET /browser/default → file tree in sidebar → click file → Markdown rendered as HTML
```

Path traversal protection tested: `?path=../../etc/passwd` correctly returns 404.

## 2025-04-23 — v1 delivered

After a long pause for other projects, v1 is done. See [release notes in the roadmap](../../projects/alpha/planning/roadmap.md).

---

*See: [Project Alpha](../../projects/alpha/overview.md) — [Style Guide](../../styleguide.md)*
