---
id: ailments-and-status
title: Ailments and Status Effects
summary: Ignite, bleed, poison, shock, chill, freeze, stun — PoE2's rules, which differ sharply from PoE1 (ailments scale from hit damage).
order: 6
---

PoE2 rebuilt the ailment system. The headline rule: **damaging ailments derive
from the damage of the hit that applied them** — you scale them primarily by
scaling the hit.

## Damaging ailments

- **Ignite** (fire): burns for a portion of the igniting hit's fire damage over
  its duration. Chance to ignite comes from gems/tree/gear; magnitude comes from
  the hit and "increased Magnitude of Ignite"-style modifiers.
- **Bleed** (physical, attacks): only the **strongest bleed instance** on a target
  deals damage at a time; aggravated bleed (e.g. against moving targets or via
  specific effects) deals more.
- **Poison** (physical+chaos): **stacks** — multiple instances tick together
  (stack limits and rules have shifted between patches; verify current behavior
  before building around unlimited stacking).
- Because ailments scale off the **pre-mitigation hit**, "more damage" multipliers
  on the hit also grow the ailment. Dedicated ailment builds then add duration
  and magnitude modifiers on top.

## Non-damaging ailments

- **Shock** (lightning): shocked enemies take increased damage. Application is
  chance/threshold-based relative to the hit and target; magnitude modifiers
  exist. Core enabler for "Cast on Shock"-style triggers.
- **Chill / Freeze** (cold): chill slows; freeze incapacitates once freeze
  buildup crosses the threshold (bigger hits build faster; bosses have high
  thresholds). Strong defensive scaling for cold builds.
- **Electrocute, Pin, and other buildup effects** exist on specific skills —
  read the skill's stat text via get_gem rather than assuming.

## Stun and armour break (physical status)

- Hits build **stun buildup** against a threshold scaling with the target's life;
  heavy stuns open combo opportunities (and threaten the player in reverse).
- **Armour break** temporarily removes enemy armour after enough physical
  punishment — a physical-build damage amplifier.

## Build implications

- An "ignite build" is really a **big-single-hit fire build** plus ignite chance
  and magnitude/duration scaling — not a separate damage formula as in PoE1.
- Ailment-immunity/mitigation for the player: charms (auto-trigger against
  freeze/stun etc.), gear affixes ("reduced effect/duration of X on you"), and
  some tree nodes. Endgame builds should name their answer to freeze at minimum.
- Don't transplant PoE1 numbers ("ignite is 50% of hit damage over 4s") — the
  proportions are patch-tuned. State mechanics qualitatively and pull current
  numbers from gem stat text where available.
