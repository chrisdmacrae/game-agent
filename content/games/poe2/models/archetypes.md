---
id: archetypes
title: Build Archetypes and Budget Heuristics
summary: The recurring build archetypes in PoE2, what scales each one, and honest budget/effort heuristics for recommending them.
order: 10
---

Archetypes are stable patterns even as specific meta skills shift each patch. Use
them as scaffolding, then fill in current-patch specifics from the database.

## Core archetypes

- **Attack crit (weapon-based)**: scale weapon quality, crit chance/bonus, attack
  speed. Gear-hungry (weapon is most of the damage) but smooth scaling.
  Classes: Ranger/Huntress/Mercenary/Monk directions.
- **Caster hit (spell crit or raw)**: scale spell gem levels (+level gear is
  premium), cast speed, crit. Less weapon-dependent; levels and supports carry.
  Witch/Sorceress directions.
- **Ailment/DoT (ignite, bleed, poison)**: build one huge hit, then scale ailment
  magnitude/duration. Slower but tanky-friendly playstyle; bossing-oriented.
- **Minions**: spirit stacking (sceptre + gear spirit), minion gem levels, minion
  damage/life. Damage is outsourced — player invests in defense and spirit.
  Witch-centric. Count minions explicitly; each reserves spirit.
- **Triggers/meta-gem engines** (Cast on Shock/Ignite...): a generator skill
  charges energy, the meta gem auto-casts a payoff spell. Powerful but
  repeatedly rebalanced during EA — ALWAYS verify current trigger rates and
  reservations with get_gem before recommending.
- **Totem/remote**: place totems that attack/cast for you; mid-tanky playstyle,
  scaling via totem-specific and generic damage mods.
- **Tank/block/armour stackers**: defense-first with a scaling payoff (block →
  damage conversions, armour → damage). Slower clear, hardcore-friendly.

## Matching archetype to request

- "League starter / budget": prefer archetypes that work on rare gear with few
  mandatory uniques — minions, DoT, totems, generic attack builds. Avoid builds
  gated behind one expensive unique or perfect trigger setups.
- "Bossing": single-target archetypes — DoT ramp, heavy-hit crit, minion
  sustained. Needs a defensive answer to boss slams (block/ES/high armour).
- "Mapping/clear": AoE/projectile coverage, movement, freeze/shatter chains.
- "Hardcore": defense-first archetypes; res-capped early, stun/freeze answers,
  recovery redundancy. Cut greedy damage nodes.

## Budget tiers (communicate honestly)

- **Fresh league start**: quest gems, self-found rares, ~2 support sockets/skill.
- **Early currency (few exalts)**: bought rares with life+res, first build
  unique, 3rd sockets.
- **Established (divines)**: +level amulets, 4-5L main skill, chase notables via
  better jewels.
Never present an "established"-tier build to a league-start request. When
get_prices data is available, quote it; otherwise label costs as estimates.

## Standing warnings

- The meta shifts every major patch (roughly quarterly); a skill that dominated
  last league may be nerfed — check patch context via get_meta_context and avoid
  asserting "X is the best" without hedging to the data you can verify.
- Unreleased content (classes like Marauder/Duelist/Templar appear in data but
  are not playable) — is_released flags in the data mark this; never recommend
  unreleased gems/classes.
