# Build Your Build — design system

Build Your Build is a theorycrafting toolkit for games. Each supported game gets a public
landing page ("hub") that does two jobs: it tells you how to connect the Build Your Build
MCP server to Claude or ChatGPT, and it lists builds other players have published — sortable
and filterable by game-specific attributes (class, ascendancy, game stage, patch, budget).

The reference points named in the brief are sites like mobalytics.gg and maxroll.gg: dense,
data-forward game resources. The brief explicitly asked **not** to reuse their palettes, so
this system is arcane teal + rune magenta on cold slate, not orange-on-black or purple-blue.

## Sources

No codebase, Figma file, deck, or brand asset was supplied. Everything here was authored from
the written brief (product description + the "own brand and voice" instruction). Consequences,
stated plainly:

- **No logo.** The brand appears as a type-only wordmark set in Bricolage Grotesque 800
  (`guidelines/wordmark.html`). Nothing was drawn or reconstructed.
- **Fonts are substitutions.** No font files were given. Bricolage Grotesque / Public Sans /
  Azeret Mono are vendored from Google Fonts (OFL) into `assets/fonts/` — see Caveats.
- **Icons are Lucide**, copied as real SVG files into `assets/icons/` (47 glyphs).
- Game names, patch numbers, build names, authors and stats in the UI kit are placeholder
  content for layout only.

---

## Content fundamentals

**Voice: a good player explaining a build to another player.** Flat, specific, unhyped. The
product's value is that the numbers are real, so the copy defers to numbers instead of
adjectives.

- **Person.** Second person for instructions ("Connect the MCP server", "Check the numbers
  before it goes live"). First-person plural only for the operator's own disclaimers ("Numbers
  are simulated by the publisher, not by us"). Never "I".
- **Casing.** Sentence case for every heading, title and button label written in prose.
  UPPERCASE is a *typographic* treatment applied to mono labels, eyebrows and button text —
  never typed into the copy itself.
- **Imperatives, no hedging.** "Publish it and it lands on this page." Not "You can optionally
  choose to publish…".
- **Numbers over claims.** "12,480 builds · 0.3.1 · updated hourly" instead of "a huge library
  of builds". If a claim can be a figure, make it a figure.
- **Game vocabulary is used exactly.** Class, ascendancy, patch, divine, tier, mapping,
  bossing, league start. Never softened into "character type" or "difficulty level". Stage
  names are the four fixed values: Leveling, Mapping, Endgame, Bossing.
- **Honest about limits.** Warnings are stated, not buried: "Untested on 0.3.1",
  "Fragile above tier 14 unless you buy resistances", "Gear list complete ✗".
- **No emoji, ever.** Status is carried by an icon + a color token.
- **Length.** Headings up to ~12 words; body paragraphs 1-3 sentences; card summaries two
  sentences max; mono meta lines are fragments joined by `·`.

Examples in the voice:

> Theorycraft with your assistant, publish for everyone else.
> Connect the MCP server, describe what you want to play, and get a build back with real numbers.
> Freeze-locks packs with Cold Snap, then detonates them. Stays cheap until you chase +levels.
> Your assistant filled most of this in. Check the numbers before it goes live.

Anti-patterns: exclamation marks, "unlock", "supercharge", "level up your gameplay", em-dash
aphorisms, rhetorical questions as headings, "we're excited to".

---

## Visual foundations

**The mental model: an instrument panel, not a magazine.** Cold slate chrome, hairline rules,
mono numbers, one saturated accent doing the pointing.

**Color.** Page is `--ink-900` (#0E1116), cards `--ink-800`, wells `--ink-950`. Arcane teal
`--teal-400` (#2DE1C2) is the single primary — CTAs, active nav, positive deltas, class names.
Rune magenta `--mag-400` is the accent: publish actions and the Endgame stage, nothing else.
Gold / red / blue are status-only (top tier, danger, info). Never more than two saturated
colors visible in one region. Text is `--fg-1` on surfaces, `--fg-2` for secondary, `--fg-3`
for mono meta. Two domain taxonomies are fixed and must not be recolored: `--stage-*`
(Leveling teal, Mapping blue, Endgame magenta, Bossing gold) and `--tier-*` (S gold, A teal,
B blue, C grey).

**Type.** Three families, strictly divided by job. Bricolage Grotesque 700-800 with -2%
tracking for display and h1/h2 — tight leading (0.94-1.2), always sentence case. Public Sans
for all reading text (17/15/13). Azeret Mono for **every number, patch string, stat, config
snippet and uppercase label**; the label style is mono 11/700 uppercase at .14em tracking.
If it's a quantity, it's mono. If it's a caption, it's mono uppercase.

**Spacing & layout.** 4px base with a 2px half-step (`--sp-1` … `--sp-13`). Card padding 16px
(`--sp-5`), feature panels 24-32px, stat strips separated by 32px, sections by 40px. Content
maxes at 1240px with 24px gutters; the filter rail is a fixed 264px. Controls are 30/38/46px
and buttons, inputs and selects share those heights so toolbars line up. The top bar is a
60px sticky glass strip (`--overlay-glass` + `--blur-glass`); the game strip below it scrolls
horizontally. Nothing else is fixed or sticky.

**Backgrounds.** No photography, no illustration, no gradient washes. Two devices only: flat
slate surfaces, and a 32px hairline grid (`--texture-grid`) used on hero and build-header
panels — the same "planner canvas" cue on both. Where text ever sits over artwork, it gets
`--scrim-bottom` (a bottom-up near-black scrim), never a blur capsule.

**Borders, radii, cards.** Everything is bounded by a 1px `--border-subtle` hairline; hovered
or focused surfaces step to `--border-strong` or `--border-accent`. Radii are mechanical:
3px chips, 5px controls, 8px cards, 12px modals, pill only for `Tag`. Cards are
`--surface-card` + hairline + `--shadow-1` (a 1px inset top highlight plus a tight drop) —
no glow, no gradient, no scale-on-hover. A card's only accent is a **2px top edge** carrying
its stage color; a colored left border is never used.

**Shadows & glow.** `--shadow-1` cards, `--shadow-2` popovers/toasts, `--shadow-3` modals,
`--shadow-inset-well` for input wells. Accent glows (`--glow-teal`, `--glow-mag`) appear only
on a hovered primary CTA — never on cards, never at rest.

**Transparency & blur.** Two places: the sticky top bar and the dialog scrim
(`--surface-overlay` at 78% + `--blur-glass`). Soft accent fills (`--surface-accent-soft`,
10% teal) mark selected chips, active icon buttons and step numerals. No frosted cards.

**Motion.** Fast and mechanical: 90-220ms, `--ease-out` for state changes, `--ease-snap` for
the switch knob. Hover transitions on background/border/color only. Press = 1px downward
nudge, never a scale. No bounce, no parallax, no entrance animation on page load; toasts fade
in place. Prefers-reduced-motion should drop transitions entirely.

**Interaction states.** Hover *lightens* (surface up one step, border up one step, muted text
to full); primary buttons lighten to `--teal-300` and pick up the glow. Press darkens
(`--teal-500`) and drops 1px. Focus is a 2px `--focus-ring` outline at 2px offset; inputs also
take a 3px 16%-teal halo. Disabled is 40% opacity with `not-allowed`. Selected uses the soft
teal fill + teal border, never a filled block.

**Imagery vibe.** If real art is ever introduced it should read cool, desaturated and dark —
teal-shifted shadows, no warm bloom, no grain overlays. Screenshots sit inside a hairline
card at 8px radius.

---

## Files

| Path | What |
| --- | --- |
| `styles.css` | Entry point — nothing but `@import` lines. Consumers link this. |
| `tokens/colors.css` | Ramps + semantic surface / text / border / intent / stage / tier tokens |
| `tokens/typography.css` | Font stacks, display / heading / body / mono / label composites |
| `tokens/spacing.css` | Spacing scale, radii, control heights, layout constants |
| `tokens/effects.css` | Shadows, glows, scrims, grid texture, glass |
| `tokens/motion.css` | Durations, easings, the shared control transition |
| `tokens/fonts.css` | `@font-face` rules for the vendored variable TTFs |
| `tokens/base.css` | Reset, link colors, `.byb-label` helper |
| `guidelines/*.html` | 21 specimen cards (Colors, Type, Spacing, Brand) |
| `assets/icons/` | 47 Lucide SVGs |
| `assets/fonts/` | Bricolage Grotesque, Public Sans (+italic), Azeret Mono variable TTFs |
| `ui_kits/web/` | Click-through recreation of the web app — see its README |
| `SKILL.md` | Agent Skills front matter for use outside this project |

## Components

Grouped by concern under `components/`. Each has a `.jsx`, a `.d.ts` props contract, a
`.prompt.md` usage note, and one `@dsCard` HTML per directory.

- **core/** — `Button`, `IconButton`, `Icon`, `Badge`, `Tag`, `Card`
- **forms/** — `Input`, `Select`, `Checkbox`, `Radio`, `Switch`
- **navigation/** — `Tabs`
- **feedback/** — `Dialog`, `Toast`, `Tooltip`
- **builds/** — `BuildCard`, `StatBlock`, `CodeBlock`

Intentional additions beyond a standard primitive set:

- `Icon` — a masked-SVG wrapper so Lucide glyphs inherit `currentColor`; without it every
  screen hand-rolls an `<img>`.
- `BuildCard` — the published build is the product's primary object; it appears on every hub.
- `StatBlock` — the label-over-mono-figure atom that every stat strip repeats.
- `CodeBlock` — the MCP connect flow is mostly copyable config, so it needs a real component.

## Iconography

Lucide (1.5px stroke, 24px grid, outline only) is the icon system, copied into
`assets/icons/` as individual SVGs — no icon font, no sprite sheet, no CDN dependency at
runtime. **This is a substitution:** the brief supplied no icon set, and Lucide was chosen for
its thin uniform stroke, which matches the hairline-rule aesthetic.

Rules: always render through `<Icon name="…" />` so the glyph inherits `currentColor`.
Sizes are 11px (inside badges/labels), 13-14px (inline with body text, small buttons),
16px (default, buttons and list rows), 20px (panel headers, game tiles). Icons are never the
only carrier of meaning — they pair with a label or a tooltip. Never fill an icon, never
recolor it outside the token set, never rotate one except the 180° chevron used for "back".

Icon vocabulary in use: `flame` endorsements/heat, `shield` defence/EHP, `swords` combat,
`gauge` overview, `plug-zap` MCP connection, `terminal` config, `copy`/`check` copy state,
`funnel`/`sliders-horizontal` filtering, `layout-grid`/`list` view switch, `crown`/`skull`/
`sword`/`clock` game marks, `user` author, `clock` recency, `triangle-alert` outdated data,
`book-open` docs, `chevron-down`/`chevron-right` disclosure.

No emoji. No unicode pictographs. The only decorative glyphs are the `/` separators in the
wordmark and the `·` separator in mono meta lines.

## Caveats

- **Fonts are stand-ins.** Bricolage Grotesque (display), Public Sans (body) and Azeret Mono
  (data) are Google Fonts vendored as variable TTFs. If Build Your Build has real licensed
  faces, drop them into `assets/fonts/` and rewrite `tokens/fonts.css`.
- **No logo exists.** Anywhere a mark belongs, the type-only wordmark or the 34px `BYB`
  square is used.
- **One product surface.** The brief describes a public web app only. No mobile app, docs
  site, or deck template was described, so none was invented.
