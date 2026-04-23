# Project Beta — Overview

**Status:** Planning  
**Proposed start:** 2025-06-01

## Concept

Project Beta will explore adding real-time collaboration to mdtree using Laravel Reverb (WebSockets). Two users editing the same file should see each other's cursors and changes propagate without conflicts.

## Open Questions

1. How do we handle merge conflicts in Markdown?
2. Should we use CRDTs (Yjs) or operational transforms?
3. Is TipTap's collaboration extension compatible with our `@tiptap/extension-markdown` setup?

## Rough Plan

- [ ] Research Yjs + TipTap collaboration
- [ ] Spike: Reverb channel per open file
- [ ] Design conflict resolution strategy
- [ ] Prototype with two browser tabs

## Dependencies

This project depends on [Project Alpha v2](../alpha/overview.md) completing the TipTap editor integration first.

---

*See: [Alpha overview](../alpha/overview.md) — [Root](../../readme.md)*
