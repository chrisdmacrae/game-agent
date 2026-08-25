---
paths:
  - 'resources/js/**/*.vue'
---

# Js

## byb Button: icon props do not work with as-child
`<Button as-child>` renders the child (usually an Inertia `<Link>`) as the root via reka-ui `Primitive`, so the `icon` / `iconRight` glyphs render as siblings *outside* the button box. When you need a link-button with an icon, drop the `icon`/`iconRight` prop and put `<Icon>` inside the `<Link>` — the button's `inline-flex items-center gap-2` classes are merged onto the Link, so it lines up.
