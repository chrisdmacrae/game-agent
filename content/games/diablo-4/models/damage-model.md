---
id: damage-model
title: Damage Model (buckets and multipliers)
summary: How Diablo 4 computes a hit — the single additive bucket, main stat, discrete [x] multipliers, crit/Vulnerable/Overpower, Lucky Hit and DoT. The one model most transferred ARPG intuition gets wrong.
order: 2
---

**Scope note**: this toolkit does NOT simulate DPS. Use this model to reason about
*what scales what* and to rank options in algebra terms. For exact figures, point
users at the Maxroll planner / in-game Advanced Tooltip Information (Options →
Gameplay), which labels every stat `[+]` or `[x]`.

## The order of operations

A hit resolves roughly in this order:

1. **Weapon damage × skill %** (the skill's own coefficient)
2. **× the Additive Damage Multiplier** — one shared bucket, see below
3. **× the Main Stat multiplier**
4. **× every Global `[x]` multiplier, each applied separately**
5. **− the enemy's damage reduction** (and any `[x]` debuffs you applied)

Everything a build does is an attempt to move value out of step 2 and into
steps 3 and 4.

## The single additive bucket (the D4-specific trap)

Almost every `[+]% damage` line in the game lands in **one** bucket that starts
at 100% and sums:

- Damage to Close / Distant, Damage to Injured / Healthy / Crowd Controlled
- Core Skill Damage, Basic Skill Damage, and the other skill-tag categories
- Elemental damage (Fire, Cold, Lightning, Physical, Poison, Shadow, Holy)
- **Critical Strike Damage**, **Vulnerable Damage**, **Overpower Damage**
- most paragon board stats, most gear affixes, most passive ranks

Because they sum, each addition is worth **less** than the last. Adding +60%
additive when you already have +700% is a ~7% real gain, not 60%. This is the
central budgeting fact of D4 itemization: an affix that reads bigger is often
worth less than a small conditional `[x]`.

`[x]` multipliers — legendary aspects, legendary paragon nodes, skill variant
nodes, unique powers, some passives — each multiply the whole product on their
own. **A single `[x]`20% is frequently worth more than several hundred percent
of additive at endgame density.** Builds are largely a hunt for stacked `[x]`
conditions whose uptime you can actually sustain.

One wrinkle: affixes explicitly worded as a **"Multiplier"** (e.g. "Critical
Strike Damage Multiplier", "Damage over Time Multiplier") sum *within their own
named category* and then multiply. Read the wording literally — "Critical Strike
Damage" and "Critical Strike Damage Multiplier" are different stats in different
places in the formula. Use `search_affixes` to confirm which wording a slot
actually rolls before promising a build a multiplier it cannot get.

## Main stat

Main stat is its own multiplier, not part of the additive bucket, so it never
suffers the bucket's diminishing returns against additive stacking — it is a
reliable, always-on scaler.

| Class | Main stat |
| --- | --- |
| Barbarian, Paladin | Strength |
| Sorcerer, Necromancer | Intelligence |
| Rogue, Spiritborn | Dexterity |
| Druid, Warlock | Willpower |

Every attribute also has a universal secondary effect regardless of class:
Strength → Armor, Intelligence → all Resistance, Willpower → Overpower damage and
healing received, Dexterity → Dodge. This is why paragon "+5 to an attribute"
filler nodes are never dead points, and why rare-node attribute thresholds are
reachable at all.

## Critical Strike

Two separate contributions, and conflating them is a common error:

- **Base crit bonus** is a `[x]` multiplier applied when a hit crits.
- **`[+]` Critical Strike Damage** goes into the additive bucket, so it decays
  like everything else there.
- **Critical Strike Chance** determines how often either applies. Chance and
  damage are complementary — an extra point of chance is worth more the larger
  your crit damage is, and vice versa.

A build either commits to crit (chance from gear/temper/passives, plus crit-only
`[x]` aspects) or ignores crit lines entirely. Half-commitment is the worst
outcome. Note DoTs do not crit by default (below).

## Vulnerable

Vulnerable is a debuff you apply. It has a baseline `[x]` multiplier that all
characters get, plus `[+]` Vulnerable Damage in the additive bucket, plus
skill/aspect-specific Vulnerable `[x]` lines.

The number on the affix is not the story — **uptime is**. A build must name the
skill, passive, or aspect that applies Vulnerable and say how reliably it covers
the target. Vulnerable Damage affixes on a build with 30% uptime are mostly dead
stats. Historically this is the single most over-valued affix by new players.

## Overpower

Overpower is a periodic empowered hit (it fires on a timer for normal attacks,
and some skills/aspects guarantee it). Its size scales off your **current Life
plus Fortify**, which is why Overpower builds stack maximum Life and Fortify as
*offensive* stats — an inversion of the usual ARPG separation between offense and
defense. Willpower adds Overpower damage; `[+]` Overpower Damage is additive.

Overpower builds live and die on **guaranteeing** the Overpower rather than
waiting for the timer. If a build claims Overpower scaling, it must name the
source of guaranteed procs.

## Lucky Hit

Lucky Hit is **not** damage — it is D4's proc-coefficient system.

- Every skill has a base Lucky Hit Chance (its proc coefficient), visible with
  Advanced Tooltips on.
- Every Lucky Hit *effect* has its own chance. The real trigger rate is the
  skill's chance × the effect's chance, further scaled by `+% Lucky Hit Chance`.
- Effects triggered by a Lucky Hit generally cannot themselves generate Lucky
  Hits — no proc chains.
- Heal / restore-resource Lucky Hit effects use a fixed chance; you scale the
  amount recovered rather than the frequency.

Consequence: fast, many-hit skills with a *low* per-hit coefficient are not
automatically better Lucky Hit engines than slow high-coefficient skills. Check
the skill's actual coefficient with `search_skills` before building a Lucky Hit
engine around it.

## Damage over Time

DoTs are a distinct damage path with their own rules:

- Types map to damage elements: Bleeding (physical), Burning (fire), Frostbite
  (cold), Sparking (lightning), Poisoning (poison), Corrupting (shadow). Class
  availability differs — verify with `search_skills`.
- DoTs tick on a fixed short interval; total damage is spread across the
  duration, so the DoT's *total* is what scaling changes, not the tick.
- **DoTs cannot Critical Strike by default.** Specific class mechanics and
  aspects are the exceptions; never assume crit investment helps a DoT build
  without naming the source that enables it.
- Many bonuses (including Vulnerable) are **snapshotted** at application: the
  DoT keeps the multiplier it was applied with even if the buff or debuff
  expires. This rewards applying DoTs during burst windows.
- Applying the same targeted DoT again combines with the existing one rather
  than stacking a fresh independent instance; ground effects and non-targeted
  sources stack separately. Application *rate* therefore has a ceiling.
- Duration extensions raise the ceiling on how much DoT can be stored on a
  target — duration is an offensive stat for these builds.

## Speed, breakpoints, and caps

- Attack Speed and Cast Speed have their own caps and are effectively separate
  pools; several skills convert speed into damage only at discrete
  **breakpoints**, so gains between breakpoints do nothing. Never claim a flat
  "X% more attack speed = X% more DPS".
- Cooldown Reduction and Movement Speed are hard-capped. Resource Cost Reduction
  stacks multiplicatively and never reaches zero cost.
- Exact cap values move between patches — say "capped, verify current value"
  rather than quoting a number you did not look up.

## Where D4 intuition differs from other ARPGs

State these plainly when a user brings PoE/Last Epoch habits:

1. There is essentially **one** additive bucket, not a bucket per tag. Stacking
   more `[+]` is the weakest form of scaling in the game.
2. `[x]` multipliers are rare, mostly conditional, and are the actual build.
3. Armor mitigates *all* damage types, not just physical (see the defenses doc).
4. Defensive stats (Life, Fortify) can be offensive stats via Overpower.
5. Crit does not touch DoT by default.
6. "Lucky Hit" is a proc system, not a damage stat.

## Comparing options honestly

When ranking two choices, do the algebra out loud: state the build's approximate
additive total, then show that the `[x]` option multiplies the whole product
while the `[+]` option divides into a large sum. If the two are close, say they
are close and say the toolkit does not simulate. Never invent a DPS number.
