---
paths:
  - app/Models/Build.php
---

# Models

## The build jsonb payload is the source of truth
`builds.build` (jsonb) holds the whole build. The columns next to it (class, ascendancy, stage, tier, level, dps, ehp, cost_divine, hardcore_viable) are promoted copies for filtering/sorting only — never write them directly. Build::syncPromotedFields() derives them, and a `saving` model hook runs it whenever the payload is dirty. Builds are NOT normalised into per-entity tables; add new build fields to the payload, not to new tables.

## Build URLs are game-namespaced; use Build::url()
The canonical build page is `/{game}/build/{publicId}` (route `games.builds.show`). Always link with `Build::url()` — it resolves the game slug — never `route('builds.show', ...)`, which is the legacy `/builds/{publicId}` URL kept only to 301 onto the canonical one. Build pages 404 when reached through the wrong game slug.
