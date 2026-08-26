---
paths:
  - 'app/Domain/Builds/**'
---

# Domain Builds

## Per-game build behaviour goes through GameBuildProfile
Build anatomy is per-game, so nothing outside `app/Domain/<Game>/` may name a game's classes directly. `GameBuildProfile::for($game)` / `::forBuild($build)` is the one seam: it hands back that game's payload rules, normalizer, validator, publish checks, class/ascendancy/tier lists, page enrichment and tree props, plus the og kicker and whether the game exports to Path of Building.

It matches on slug and anything that is not `diablo-4` falls back to PoE 2 — deliberate, because Game::factory() mints throwaway slugs and legacy rows predate the game namespace. Adding a game means one more branch per method here, plus a `<Game>PublishChecks` class; do not build a registry for it.

PublishChecklist keeps only the game-agnostic checks (stats, patch) and splices `$profile->publishChecks($build)` between them, so the returned shape and the `PublishChecklist::check()` helper stay shared. BuildUpdateRequest picks its rules off the route's `{game:slug}` binding, which is the same game the controller loads the build from.
