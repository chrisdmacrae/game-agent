# Build Your Build — v1 scope document

For Claude Code. Purpose: compare this scope against the current Laravel app and report what
is missing, what exists but is unbranded, and what needs reworking. **Assess and report before
implementing.**

The prototype lives at `Build Your Build v1.dc.html` in this project. It is a **design
reference** — an HTML/React prototype showing intended structure, copy and behaviour. Do not
port it verbatim into the Laravel app; recreate its screens using the app's existing stack
(Blade / Livewire / Inertia — whatever is already there) and the Build Your Build design
system tokens.

---

## 1. Read this first: the passive tree

**Ignore the passive tree visualisation in the prototype.** The prototype's tree is a
placeholder — a synthetic three-branch graph with ~40 nodes, invented for layout only. It is
not PoE 2 data and it is not the intended interaction model.

Use the passive tree **already implemented in the codebase**. The only work there is:

- Re-skin it to the design system: page `--ink-900`, canvas `--ink-950`, allocated nodes
  `--teal-400` (minor nodes `--teal-500`), connected-but-unallocated stroke `--teal-600`,
  unallocated fill `--ink-800` with `--ink-500` stroke, selected node stroke `--fg-1`.
- Node labels: mono 11/700 uppercase, `.14em` tracking (`--type-label`), `--teal-300` when
  allocated, `--fg-3` when not.
- Point counter: mono, `--fg-1`, turns `--red-400` when over budget.
- Keep whatever pan/zoom, search and hit-testing the existing implementation has. The
  prototype has none of that and is not a target to match.

Everything else in this document is the actual scope.

---

## 2. Product shape

Four route groups, one visual system, no separate logged-in app:

| Route | Public | Signed in adds |
| --- | --- | --- |
| `/` root landing | full | topbar gains **My builds** + account chip |
| `/[game]` game hub (PoE 2 live) | full | a **Your builds** strip above the public list |
| `/[game]/build/[id]` build page | full | Edit button on builds you own; drafts visible to owner only |
| `/[game]/build/[id]/edit` | — | owner only |
| `/my-builds` | — | signed in only |
| `/settings` | — | signed in only |
| `/login`, `/login/sent`, `/login/verify` | full | — |
| `/[game]` for unsupported games | waitlist/vote page | — |

**Rule to enforce:** logged-out is a strict subset of logged-in. There is no separate
dashboard. Delete the default Laravel dashboard; `/my-builds` replaces it.

Games in v1: Path of Exile 2 (patch `0.5.2`, live). Queued, not live: Last Epoch, Diablo IV,
World of Warcraft. **Elden Ring is out — remove it everywhere.**

MCP endpoint: `https://buildyourbuild.com/mcp/[engine-slug]`, e.g.
`https://buildyourbuild.com/mcp/poe2`. One endpoint per game.

---

## 3. Screen scope

### 3.1 Root landing (`/`)

Sections, in order:

1. **Hero** on the hairline grid texture (`--texture-grid`, 32px). Eyebrow "MCP SERVER ·
   CLAUDE & CHATGPT", display-xl headline, one paragraph, two CTAs (teal primary "Connect the
   server" opens the connect dialog; ghost "Browse PoE 2 builds"). Stat strip: builds
   published, games live, PoE 2 patch, data refresh.
2. **How it works** — three cards: add the server, approve the tools, ask then publish.
3. **Sample assistant conversation** — four alternating message bubbles, assistant messages
   carry a mono meta line (`4.1M dps · 18.9k ehp · 12 div · 0.5.2`). Right column: the
   **connect panel** (see 3.2).
4. **Game grid** — one card per game, 2px top edge in the game's accent, Live/Queued badge,
   live counts or vote counts. Clicking a queued game goes to its waitlist page.
5. **FAQ** — five Q/A pairs in a two-column list, hairline rule above each.

Gap to check: does the app have a root landing page at all today, and does the topbar link
every game hub?

### 3.2 Connect flow (replaces the config-file instructions)

**No `claude_desktop_config.json` anywhere.** The server is hosted; the user pastes a URL.

A `Claude` / `ChatGPT` tab pair switches a four-step numbered list, followed by a copyable
code block containing the server URL.

- Claude: Open Settings → Connectors → Add custom connector → paste URL → Connect.
- ChatGPT: Open Settings → Connectors → Advanced → Developer mode → Create → paste URL →
  Connect.

This panel appears in three places: root landing, game hub connect panel, and the "Connect
MCP" dialog reachable from the topbar on every page. One implementation, three mounts.

### 3.3 Magic link auth (`/login`)

Three states, all on the grid texture, centred 420px card, wordmark above:

1. **Request** — email input, "Send magic link" (lg primary, full width), footnote that
   browsing needs no account. Invalid email shows a danger toast, not an inline redirect.
2. **Sent** — the address echoed in a mono well, Resend, Change email, mono line
   "Expires in 15:00 · one use".
3. **Verifying** — progress bar, mono token line, then redirect to `/my-builds` with a
   success toast.

Gap to check: the existing magic link flow is functional but unbranded. Scope is a re-skin
plus the three explicit states (the current flow likely has no "verifying" screen).

### 3.4 Game hub (`/poe2`)

- Connect panel (3.2) at the top, on the grid texture.
- **Your builds** strip — signed in only, three cards, "All my builds" + magenta "Publish
  build".
- Header row: "Published builds", game name, patch badge, sort select, grid/list toggle.
- Filter rail, fixed 264px: class checkboxes with counts, ascendancy select, game-stage
  radios, min/max divine inputs, "Current patch only" and "Hardcore viable" switches.
- Active filters render as removable tags above the results with a mono result count.
- Results grid: `BuildCard`, two columns in grid view, one in list view.

Filters must actually filter server-side or via Livewire — the prototype filters client-side
over six rows.

### 3.5 Waitlist page (unsupported games)

Magenta eyebrow "NOT LIVE YET", game name in display-l, one paragraph, stat strip (votes,
queue position, latest patch), vote card (email + magenta "Cast vote"), and a ranked queue
list with vote bars. One email, one vote per game.

Needs: a `game_votes` table and a launch-notice mailing list. Check whether either exists.

### 3.6 My builds (`/my-builds`)

- Header: "SIGNED IN AS {handle}", "My builds", Settings + Publish build.
- Stat strip: published, drafts (magenta), endorsements, member since.
- **Grouped by game, drafts pinned to the top of each group.** Each row: status badge
  (Draft magenta / Public teal), stage tag, patch, title, class/ascendancy, updated, two
  stat blocks, View + Edit.
- Groups for games with no builds show a dashed empty state pointing at the waitlist.

### 3.7 Build page (`/poe2/build/[id]`)

Header panel on grid texture with a 2px stage-coloured top edge: tier badge, stage tag,
patch badge, draft badge when unpublished, title in display-m, class/ascendancy/author/
updated line, summary, action column (Edit for owners, otherwise Endorse; Save; share /
export / more icon buttons), and a five-stat strip (DPS, EHP, cost, tier, endorsements).

Five tabs:

1. **Overview** — Offence and Defence stat tables (6 rows each), a resistance card with four
   bars against a 75% cap (under-cap values in `--red-400`), "How it plays" (3 bullets),
   "Works because" / "Watch out for" pair, leveling milestones.
2. **Skills** — one panel per skill gem: gem swatch, name, tags as outlined chips, mono
   `lvl 20 / 20% · 38 mana`, role, reported numbers, then a two-column grid of **support
   gems** with name + effect. This tab does not exist today; the data model needs support
   gems per skill.
3. **Gear** — a **gear-screen layout**, not a table: a 3-column paperdoll grid placed by slot
   (weapon / helmet / offhand, gloves / body armour / boots, amulet / — / belt, ring 1 /
   ring 2), each cell showing slot label, rarity (Unique gold, Rare blue, Magic/Normal
   neutral), item name in the rarity colour, key modifiers in mono, and a **socket row**:
   one chip per rune socket, filled chips solid-bordered, empty sockets dashed and labelled
   "empty socket". Header carries "5 of 8 rune sockets filled". Below the paperdoll: Charms
   and Flasks cards.
4. **Passives** — the existing in-code tree (see §1).
5. **Notes** — author prose, preserved line breaks.

Sidebar (Overview and Notes only): "Generated with" card (MCP + `byb://poe2/build/{id}`) and
Similar builds.

### 3.8 Build edit (`/poe2/build/[id]/edit`)

Full edit mode — a separate layout, 960px column, sticky action bar under the topbar with
title, dirty state ("unsaved changes" in gold), Cancel and Save changes.

Cards, in order:

1. AI notice: "Your assistant filled most of this in. Check the numbers before it goes live."
2. **Identity** — title input, summary textarea with a 240-char counter.
3. **Classification** — class, ascendancy (options depend on class), stage, tier selects.
4. **Stats** — DPS, EHP, cost inputs with hints.
5. **Skills and support gems** — one block per skill: name, role, level, cost, tags
   (comma-separated), reported numbers, support gems (comma-separated). Add/remove skill.
6. **Gear and runes** — one block per slot: slot, item, rarity select, key modifiers
   (comma-separated), runes (comma-separated; a blank entry means an empty socket), with a
   mono "2 sockets · 1 filled" readout. Add/remove slot.
7. **Passives** — the in-code tree in allocate mode, point counter, Reset, import string.
8. **Notes and milestones** — notes textarea plus add/remove milestone rows (level + text).
9. **Visibility** — Draft / Public radios plus a pre-flight checklist (stats present, gear
   list complete, passive budget, patch currency) using check / triangle-alert icons.

Saving a draft keeps it owner-only; saving public lists it on the hub. Both fire a toast.

### 3.9 Account settings (`/settings`)

760px column. Profile card: handle/gamertag and Discord username side by side, bio textarea
with a 180-char counter. Email card: email input, note that changing it sends a magic link to
the new address and the old one works until used. Save / Cancel. Danger zone: red-bordered
card, copy about what deletion removes, `danger` button, confirm dialog naming the account
email and the build counts.

---

## 4. Data model implied by the prototype

Check each against the current schema:

- `users`: handle, email, discord_username, bio, created_at.
- `games`: slug, name, short_name, patch, icon, accent, is_live, vote_count.
- `game_votes`: game_id, email, unique per pair.
- `builds`: game_id, user_id, title, summary, class, ascendancy, stage
  (Leveling/Mapping/Endgame/Bossing), tier (S/A/B/C), patch, visibility (draft/public),
  dps, ehp, cost_divine, endorsements, updated_at, tree_import_string, notes.
- `build_stats`: offence and defence key/value rows, plus resistances with a cap.
- `build_skills`: name, role, level, quality, cost, tags[], reported numbers, sort order.
- `build_skill_supports`: skill_id, name, effect, sort order.
- `build_gear`: slot, item_name, rarity, mods[], sort order.
- `build_gear_runes`: gear_id, rune_name (nullable = empty socket), socket index.
- `build_charms`, `build_flasks`: name, note.
- `build_milestones`: level, text.
- `endorsements`, `saved_builds`: user_id, build_id.

The MCP server writes builds; the web app must accept a partial build and let a human finish
it. Publishing is gated on the pre-flight checks in §3.8.

---

## 5. Design system

Tokens live at `_ds/build-your-build-design-system-04a0c5dd-88a3-4408-ab80-e177382bccdb/`.
Link `styles.css` and the token files; do not re-declare values. Components used by the
prototype: Button, IconButton, Icon, Badge, Tag, Card, Input, Select, Checkbox, Radio, Switch,
Tabs, Dialog, Toast, Tooltip, BuildCard, StatBlock, CodeBlock.

Hard rules: one teal primary CTA per view; magenta only for publish/claim actions and the
Endgame stage; stage colour only ever on a card's 2px top edge; every number, patch string and
uppercase label in Azeret Mono; `--stage-*` and `--tier-*` are fixed taxonomies and must not
be recoloured; icons are Lucide via the `Icon` wrapper; no emoji.

Voice: flat, second person, sentence case in prose, uppercase only as a typographic treatment,
numbers instead of adjectives, limits stated plainly ("Untested on 0.5.2").

---

## 6. What to report back

For each item below, say **exists / exists but unbranded / missing**, with the files you
checked:

1. Root landing page and topbar game links.
2. Connect instructions — confirm the config-file JSON is gone from every template.
3. Magic link: request, sent, verifying states and their branding.
4. Game hub: connect panel, filter rail, sort, grid/list, "Your builds" strip.
5. Waitlist page, `game_votes`, launch-notice list.
6. `/my-builds` grouped by game with drafts pinned; removal of the default Laravel dashboard.
7. Build page: overview panels, resistances, skills tab with support gems, gear paperdoll with
   rune sockets.
8. Build edit: full edit mode, per-section fields, pre-flight checks, draft/public.
9. Settings: handle, email change flow, Discord, bio, delete account.
10. Passive tree: confirm the existing implementation is reused and list the re-skin work only.

Flag anything in this document that conflicts with a constraint already in the codebase rather
than working around it silently.
