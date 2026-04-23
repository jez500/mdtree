# Markdown Style Guide

A complete reference for all supported Markdown syntax. Use this file to verify rendering as the editor evolves.

---

## Headings

# Heading 1
## Heading 2
### Heading 3
#### Heading 4
##### Heading 5
###### Heading 6

---

## Paragraphs & Line Breaks

This is a paragraph. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.

This is a second paragraph separated by a blank line.

Lines within a paragraph  
can be broken with two trailing spaces (like this line).

---

## Emphasis

**Bold text** using double asterisks.

*Italic text* using single asterisks.

***Bold and italic*** combined.

~~Strikethrough~~ using double tildes.

`Inline code` using backticks.

---

## Blockquotes

> A simple blockquote.

> A longer blockquote with multiple lines.
> The quote continues here.
>
> And after a blank line, it continues in a new paragraph.

> **Nested blockquote:**
>
> > This is nested one level deeper.
> >
> > > And this is two levels deep.

---

## Lists

### Unordered Lists

- Item one
- Item two
  - Nested item A
  - Nested item B
    - Deeply nested item
- Item three

### Ordered Lists

1. First item
2. Second item
   1. Nested ordered item
   2. Another nested item
3. Third item

### Task Lists

- [x] Completed task
- [x] Another completed task
- [ ] Pending task
- [ ] Another pending task
  - [x] Completed sub-task
  - [ ] Pending sub-task

---

## Code

### Inline Code

Use `php artisan migrate` to run migrations. Call `array_map()` on the result.

### Fenced Code Blocks

```php
<?php

namespace App\Services;

class FileTreeService
{
    public function tree(string $rootPath, array $extensions): array
    {
        $realRoot = realpath($rootPath);

        if ($realRoot === false || ! is_dir($realRoot)) {
            return [];
        }

        return $this->buildTree($realRoot, $realRoot, $extensions);
    }
}
```

```typescript
import { computed, ref } from 'vue';

const count = ref(0);
const doubled = computed(() => count.value * 2);

function increment() {
    count.value++;
}
```

```bash
# Install dependencies
npm install marked dompurify @types/dompurify

# Build assets
npm run build

# Run tests
php artisan test --compact
```

```json
{
    "workspaces": {
        "default": {
            "name": "My Notes",
            "path": "/home/user/notes"
        },
        "work": {
            "name": "Work Notes",
            "path": "/home/user/work-notes"
        }
    },
    "extensions": ["md", "txt"]
}
```

---

## Horizontal Rules

Three or more hyphens:

---

Three or more asterisks:

***

Three or more underscores:

___

---

## Links

[External link to GitHub](https://github.com)

[Link with title](https://github.com "Visit GitHub")

[Relative link to readme](readme.md)

[Link to getting started](getting-started/first-steps.md)

[Link to project alpha overview](projects/alpha/overview.md)

[Link to reference shortcuts](reference/shortcuts.md)

Auto-linked URL: https://example.com

---

## Images

![Alt text for an image](https://via.placeholder.com/600x200/4f46e5/ffffff?text=Placeholder+Image)

Image with title:

![Smaller placeholder](https://via.placeholder.com/300x100/10b981/ffffff?text=Image+Title "Image tooltip title")

---

## Tables

### Simple Table

| Column A | Column B | Column C |
|----------|----------|----------|
| Cell 1   | Cell 2   | Cell 3   |
| Cell 4   | Cell 5   | Cell 6   |

### Alignment

| Left-aligned | Centre-aligned | Right-aligned |
|:-------------|:--------------:|--------------:|
| Alpha        | Beta           | Gamma         |
| 1            | 2              | 3             |
| Lorem ipsum  | dolor sit      | amet          |

### Table with Code

| Command                     | Description              |
|-----------------------------|--------------------------|
| `php artisan serve`         | Start dev server         |
| `npm run dev`               | Start Vite dev server    |
| `php artisan test`          | Run test suite           |
| `vendor/bin/pint --dirty`   | Fix code formatting      |

---

## HTML (raw)

<details>
<summary>Click to expand a details block</summary>

This content is hidden by default and revealed on click. Useful for collapsible sections in documentation.

- It can contain lists
- And other markdown

</details>

<mark>Highlighted text</mark> using the HTML mark element.

Superscript: E = mc<sup>2</sup>

Subscript: H<sub>2</sub>O

---

## Footnotes

Here is a sentence with a footnote.[^1]

Another sentence with a different footnote.[^note]

[^1]: This is the first footnote content.
[^note]: This is a named footnote with more detail. It can span multiple lines if indented.

---

## Definition Lists

Term One
: Definition of term one. Can be a longer description that wraps.

Term Two
: First definition of term two.
: Second definition of term two.

---

## Emoji

:tada: :rocket: :fire: :white_check_mark: :warning: :bulb:

(Emoji rendering depends on the Markdown parser configuration.)

---

## Escaping Characters

Use a backslash to escape special characters:

\*Not italic\* \`Not code\` \[Not a link\]

---

## Long Content (Scroll Test)

This section exists to test how the viewer handles long documents and scrolling behaviour.

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.

At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi.

---

*Last updated: 2025-04-23 — See also: [readme](readme.md), [getting started](getting-started/first-steps.md)*
