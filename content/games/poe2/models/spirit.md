---
id: spirit
title: Spirit and Reservation Budgeting
summary: Spirit is the resource that powers persistent effects — auras, heralds, permanent minions, and meta gems. Every build has a spirit budget to allocate.
order: 3
---

**Spirit** replaces PoE1's mana reservation. It is a separate resource that is
*reserved* (not spent) by persistent effects. Reserved spirit stays reserved while
the effect is active.

## What costs spirit

- **Persistent buffs / auras / heralds** (e.g. Arctic Armour, heralds, purity-style
  auras). Each has a flat spirit reservation — get_gem reports it as
  `spirit_reservation` / `costs_and_reservations`.
- **Permanent minions** (skeletal warriors, etc.): each summoned minion reserves
  spirit; higher gem levels typically reserve less per minion or allow more
  minions per point. Temporary minions (raised from corpses, time-limited) cost
  no spirit.
- **Meta gems** (Cast on X triggers): meaningful reservations (e.g. ~60).

## Where spirit comes from

- **Campaign rewards**: roughly **100 spirit** by the end of the campaign (quest
  rewards across acts). This is the baseline budget every character has.
- **Sceptres** (off-hand caster weapons): a large implicit spirit grant (+100 on
  standard sceptre bases) — this is why minion builds wield sceptres.
- **Gear affixes**: "+N to Spirit" rolls on amulets and body armour (and via
  search_mods you can verify current slots); "% increased Spirit" also exists.
- **Uniques and tree**: some uniques grant spirit; a few passives/ascendancy nodes
  interact with reservation.

## Budgeting discipline

1. Sum the reservations of every persistent effect in the build (validate_build
   does this automatically from game data).
2. Compare against realistic availability: **assume ~100 for a league-start build
   without gear investment**; 200+ implies a sceptre or dedicated gear/uniques.
3. Minion builds: spirit is the primary scaling axis — more spirit = more minions.
   Count minimum minion count needed for the build to function.
4. Leave headroom: a build that reserves 100/100 has no room for utility buffs.

## Common mistakes to catch

- Stacking three heralds/auras "because they're good" — they rarely all fit at
  league start.
- Forgetting the meta gem reservation when a trigger setup is the build's core.
- Assuming PoE1-style %-of-mana reservation — PoE2 reservations are flat numbers.
- Presenting a build without stating its spirit budget: always list
  `total reserved / total available`.
