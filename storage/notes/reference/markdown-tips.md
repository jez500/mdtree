# Markdown Tips

> Reference for common Markdown patterns. For a full syntax demo see [styleguide](../styleguide.md).

## Callout-style Blockquotes

Use blockquotes with bold labels to simulate callout boxes:

> **Note:** This is an informational callout.

> **Warning:** This is a warning. Proceed carefully.

> **Tip:** Use relative links between notes to build a connected knowledge graph.

## Linking Between Notes

Always use relative paths so your links work regardless of where the workspace is mounted:

```markdown
[See installation guide](../getting-started/installation.md)
[Project overview](../projects/alpha/overview.md)
```

Avoid absolute paths — they will break when the workspace moves.

## Embedding Code with Context

Always add a language identifier to fenced code blocks for syntax highlighting:

````markdown
```php
echo "Hello!";
```
````

Supported languages: `php`, `typescript`, `javascript`, `bash`, `json`, `yaml`, `sql`, `css`, `html`, `markdown`.

## Tables

Keep tables simple. Use alignment syntax sparingly — left-align is the most readable default:

```markdown
| Name | Value |
|---|---|
| foo | bar |
```

## Front Matter (future)

YAML front matter is planned for v3 to support tags and metadata:

```markdown
---
title: My Note
tags: [project, architecture]
created: 2025-04-23
---

# My Note

Content starts here.
```

---

*See: [Full styleguide](../styleguide.md) — [Shortcuts](shortcuts.md)*
