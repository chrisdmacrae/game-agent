---
paths:
  - 'app/Domain/D4/Meta/**'
---

# Meta

## D4 meta is an editorial tier list scraped from a Remix payload
Diablo IV has no telemetry meta and no economy data, so d4_meta_builds holds Maxroll's editorial endgame tier list. Rows are season-scoped with no game_version_id, matching the league-scoped poe2_prices precedent — a tier list tracks the live season, not the imported client patch.

The page is server-rendered Remix: the payload is inlined as `window.__remixContext = {...};` in a plain <script>, and the list lives at state.loaderData.<routeId>.post.gutenbergBlock[] where blockName === "maxroll/tierlist" -> attributes.items[]. The loader key ("branch-posts") is a route id, so scan loaders for the first one carrying post.gutenbergBlock instead of hard-coding it. Season comes from post.tags[].slug ("season-14-death-awakening"), never from appContext.branchSettings.active_season, which is stale.

Per item: name, tier, icon ("d4/barbarian" is the only class marker), link (absolute guide URL). tierIndicator is EITHER a bare string OR a {value,label} object in the same list. Tier is stored verbatim: S/A/B/C/D plus X, which means "currently bugged", not a rank.

TierListImporter replaces the source's rows in a transaction but refuses an empty parse first — never trade good rows for a silently changed page.
