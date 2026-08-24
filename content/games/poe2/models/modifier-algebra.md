---
id: modifier-algebra
title: Modifier Algebra (increased vs more)
summary: How stat modifiers combine — the single most important math rule in the game. BASE, increased/reduced (additive), and more/less (multiplicative).
order: 1
---

Every stat in Path of Exile 2 is computed with the same three-tier formula. Getting
this wrong invalidates all build reasoning, so internalize it first.

## The three modifier tiers

1. **BASE (flat)** — "Adds 5 to 10 Lightning Damage", "+40 to maximum Life".
   All flat values are summed first.
2. **increased / reduced** — "20% increased Fire Damage", "10% reduced Cast Speed".
   All applicable increased/reduced percentages are **summed together additively**
   into a single multiplier.
3. **more / less** — "40% more Damage" (typical of support gems), "30% less Damage".
   Each more/less modifier is its **own separate multiplier**, applied multiplicatively.

```
final = (Σ BASE) × (1 + Σ increased/100 − Σ reduced/100) × Π(1 ± more_i/100)
```

## Worked example

A skill deals 100 base damage. The character has three passive nodes with
"25% increased Spell Damage" each, and two support gems granting "30% more Damage"
and "20% more Damage":

```
100 × (1 + 0.25+0.25+0.25) × (1.30 × 1.20)
= 100 × 1.75 × 1.56
= 273
```

Note the increased modifiers did NOT compound with each other (they summed to 75%),
while the two more multipliers compounded.

## Consequences for build crafting

- **Diminishing returns on "increased"**: going from 0% → 100% increased doubles
  output, but 400% → 500% is only a 20% relative gain. Tree stacking has soft caps.
- **"More" multipliers are premium**: support gems and keystone effects with "more"
  scale the whole product. A 40% more support is usually worth many tree points.
- **Balance sources**: the strongest builds mix flat damage (BASE), enough increased
  from tree/gear, and as many independent more multipliers as possible.
- **Same algebra everywhere**: life, defenses, speed, area, ailment magnitude — all
  use the identical formula. "Reduced" is the negative of increased (additive);
  "less" is a multiplicative penalty.

## Reading modifier text precisely

- "increased **Spell** Damage" only applies to spells; "increased Damage" applies to
  everything. Tags on the skill gem (see its `tags`/`types` in get_gem) determine
  which modifiers apply.
- Conditional modifiers ("while on Full Life", "against Ignited enemies") only count
  when the condition holds — treat uptime honestly when comparing options.
- Added flat damage to spells vs attacks are separate stats; attacks also scale with
  weapon damage, spells do not.
