---
paths:
  - 'app/Domain/Poe2/Ggg/**'
---

# Ggg

## GGG (pathofexile.com) API traps
GGG closed new OAuth application registrations, so the whole integration is gated on `GggOAuth::enabled()` (client id + secret present). Routes 404 and the MCP character tools do not register without it — never ship a Connect button that dead-ends on their error page.

Non-negotiables their API enforces:
- User-Agent must be exactly `OAuth {clientId}/{version} (contact: {contact})`; requests without it are rejected. GggOAuth::userAgent() owns it — always go through GggOAuth::request().
- PKCE (S256) is mandatory, and authorization codes expire after 30 seconds, so the exchange runs inline on the callback, never on a queue.
- Rate limits are dynamic and announced in `X-Rate-Limit-*` / `Retry-After`. Never retry blindly; GggApiClient throws GggRateLimited and caches responses ~60s per account.

Data traps:
- PoE2 characters live behind a realm segment: `/character/poe2[/{name}]`. Without it you get PoE1 characters.
- Item `name`/`typeLine` arrive wrapped in display markup (`<<set:MS>><<set:M>><<set:S>>Grasping Mail`). Strip it or nothing matches our own data.
- Gem level/quality are inside the generic `properties` array, not fields.
- `passives.hashes` ARE our tree `node_id`s — they map straight onto a build's `passives.node_ids`.
- `class` may be the base class OR the ascendancy depending on the character; CharacterNormalizer looks it up against our Ascendancy table rather than guessing.
- The API exposes NO computed stats: no resistances, DPS, EHP or spirit. Never present a comparison of those; the diff says so explicitly in `not_comparable`.
