---
paths:
  - 'resources/js/pages/Builds/**'
---

# Builds

## Build pages are thin shells; the anatomy is game-scoped
Build anatomy is per-game (PoE 2 has gems, support gems, spirit and a passive tree; Last Epoch / D4 / WoW will not). `pages/Builds/Show.vue` and `pages/Builds/Edit.vue` must stay thin: they own SeoHead and switch on `game.slug`, then delegate to the game's renderer under `resources/js/components/games/<slug>/`. Do not add PoE 2 fields, labels or types to the page files, and name types with the game prefix (`Poe2BuildDefinition`, not `BuildDefinition`). Adding a game means a new `components/games/<slug>/` folder plus a branch in the two shells — nothing else.
