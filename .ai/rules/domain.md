---
paths:
  - 'app/Domain/**'
---

# Domain

## Read skill supports through BuildPayload
A skill's `supports` accepts either gem names or {name, effect} objects and is normalised to objects by BuildPayload::normalize() on save. Rows saved before that change still hold plain strings, so never read `$skill['supports']` directly — use BuildPayload::supportNames() (or supports() for the effects). Exporters, the enricher and BuildValidator all go through it.
