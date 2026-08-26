---
id: build-anatomy
title: Anatomy of a Complete Build
summary: Every component a finished Diablo 4 build must specify, what changes between leveling, Torment progression and Pit pushing, and the multiplier-stacking checklist to audit damage against.
order: 7
---

A "build" is a complete specification. If any component below is missing, the
build is not done. Use this as the output template.

## The nine components

1. **Identity**: class + **class mechanic configuration** (Oath, Soul Shard,
   Spirit Hall pair, Specialization, Book of the Dead layout, Spirit Boons,
   Arsenal/Technique, Enchantment slots). One sentence on the core concept and
   its win condition. The class mechanic is not optional flavour — it decides
   which skills and aspects are even legal for the build.
2. **Skill bar**: all six slots, named, with each skill's **chosen Variant and
   both Modifier picks**. A skill name alone does not specify a build.
3. **Skill tree spend**: rank targets on the skills that matter, the passives
   taken, and the capstone/key passive. State the assumed point total.
4. **Paragon**: each board in acquisition order with its rotation and entry
   edge, the Legendary node that justifies it, the glyph in each socket with a
   target rank, and which rare-node gates are being met at the final board
   count.
5. **Gear, per slot**: item and rarity, affix priorities in order, the **aspect**
   imprinted, the **tempering** manual, the **masterworking** Capstone target,
   sockets/gems, and whether it is a transfiguration target. Flag which one or
   two slots are the chase pieces and give a budget stand-in for each.
6. **Runewords**: the Ritual + Invocation pairs and what structural hole they
   fill (Unstoppable, a missing skill, resource sustain, Overflow value).
7. **Mercenary**: the **Hired** mercenary and the **Reinforcement**, with their
   skill choices and why — a defensive merc for survivability, a debuff merc
   for damage, and so on. Rapport resets each season, so note what the build
   needs at low Rapport.
8. **Seasonal power**: which of the season's powers the build uses and how it is
   configured — plus an explicit note that this layer disappears on Eternal.
9. **Defensive and progression summary**: the defenses-doc checklist answered in
   full, plus what changes between leveling, early Torment, and the build's
   final form.

## Content tiers and what each demands

**Leveling (1 → max level).** Nothing is optimized and nothing needs to be.
Requirements: a resource generator that never runs dry, one AoE spender, a
movement skill, and enough survivability to keep moving. Codex aspects are
free and are the main power source. Do **not** present the endgame skill/paragon
layout as the leveling layout — the class mechanic often is not unlocked yet,
and the endgame damage skill is frequently unusable early. Leveling advice is a
separate section, not a footnote.

**Torment progression (the long middle).** Difficulty runs through several
standard tiers and then a long ladder of **Torment** tiers, each unlocked by
clearing a corresponding Pit tier; the tier count has grown with expansions, so
verify the current ladder rather than assuming four. This is where the build
actually gets assembled: Ancestral gear, aspects on every slot, glyphs to their
first breakpoints, masterworking on the weapon, and resistances/Armor brought to
target. The binding constraint here is almost always **defense, not damage** —
players stall because they die, and the fix is the defenses checklist.

**Pit pushing (the ceiling test).** The Pit is a timed clear-and-boss run scaling
far past the Torment tiers, and it is the only place glyphs level. Pushing
demands things that mid-tier content forgives:

- **Sustained** damage against a boss with a timer, not just burst
- Clear speed that fills the monster quota inside the timer
- Survival against single hits that exceed a full health bar — meaning real
  multiplicative damage reduction and an Unstoppable answer, not just Life
- A Stagger plan for the boss (see defenses)

A build good at Torment farming is often bad at Pit pushing and vice versa. Say
which one a build is for.

## The damage-multiplier stacking checklist

Audit every damage claim against this. The additive bucket is saturated on any
real build, so the questions that matter are all about `[x]` sources and uptime:

1. **Main stat** — is the build actually stacking it, or is it drowned out by
   `[+]` affixes that do less?
2. **Aspects** — every equipped `[x]` aspect, with its condition and honest
   uptime. This is usually the largest block of a build's damage.
3. **Legendary paragon nodes** — one per board; are all of them relevant?
4. **Transformative skill variants and key passives** — often the single
   biggest multiplier in the build.
5. **Unique / Mythic powers** — worth an aspect slot only if the power is.
6. **Crit** — chance × damage, committed or ignored. Remember DoTs do not crit
   by default.
7. **Vulnerable** — the applier, and the uptime. Name both.
8. **Overpower** — only if guaranteed; if it relies on the natural timer, it is
   not a scaling plan.
9. **Debuffs on the enemy** — Vulnerable, Weaken, damage-taken increases; these
   multiply from the other side.
10. **Runeword and seasonal-power multipliers** — easy to forget, frequently
    large.
11. **Additive total** — stated once, at the end, as the thing everything else
    multiplies. If a proposed upgrade only adds to this pile, say so.

If a build's damage story is "lots of +% damage affixes", it does not have a
damage story.

## Quality gates before presenting

- Every named skill, variant, aspect, affix, unique, paragon node, and glyph was
  confirmed to exist in the **current patch** via a tool call. Names carried
  over from older seasons or from pre-expansion guides are the most common
  failure mode.
- Every number quoted traces to a tool response or is explicitly labelled as
  patch-volatile. Never invent a damage figure, a cap, or a breakpoint.
- The build answers, in one sentence each: *what is the damage button and what
  makes it big?* and *how does it not die?*
- The resource economy balances: generator, spender, and sustain named.
- Leveling path and endgame target are presented separately.

## Presentation format

Lead with three lines (concept, playstyle, what content it targets), then the
nine components as sections. Tables for the skill bar and for gear-by-slot.
Keep the leveling section clearly separated from the endgame target so a player
can follow it stage by stage, and state the paragon/point budget every layout
assumes.
