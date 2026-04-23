# December 2024

> Journal — [2025 →](../2025/january.md)

## Week 1 (Dec 2–6)

Spent time evaluating note-taking tools. Nothing quite fits the bill:

- Obsidian is great but desktop-only
- Notion has vendor lock-in
- Bear is macOS only
- LogSeq is focused on block-based writing which doesn't suit my workflow

**Decision:** Build something custom with Laravel. See [Project Alpha](../../projects/alpha/overview.md).

## Week 2 (Dec 9–13)

Started prototyping the file tree service. Key learning: `realpath()` returns `false` on non-existent paths, not an empty string. Must guard against this.

```php
$realRoot = realpath($rootPath);

if ($realRoot === false || ! is_dir($realRoot)) {
    return [];
}
```

## Week 3 (Dec 16–20)

shadcn-vue sidebar components are excellent. The `Collapsible` + `SidebarMenuSub` pattern creates clean recursive trees without custom CSS.

## Week 4 — Holiday (Dec 23–31)

Break. Reading: *A Pattern Language* (Christopher Alexander). Interesting parallels to software architecture — the idea that good structures emerge from context rather than being imposed.

> "Each pattern describes a problem which occurs over and over again in our environment, and then describes the core of the solution to that problem, in such a way that you can use this solution a million times over, without ever doing it the same way twice."

---

*Next: [January 2025](../2025/january.md)*
