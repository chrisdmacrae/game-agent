---
id: archetypes
title: Build Archetypes
summary: The recurring structural patterns D4 builds fall into, which classes host each, what scales them, and how each behaves at farming versus pushing — described as patterns, not a tier list.
order: 8
---

Specific meta skills change every patch; the underlying patterns do not. Use
these as scaffolding, then fill in current-patch specifics from the data tools.
**This is not a tier list** — do not rank archetypes; match them to what the
user is asking for.

## The patterns

**Core spam (resource dump).** A generator feeds a Core skill you press
constantly. Scales with resource generation/cost reduction, attack or cast
speed, and skill-specific `[x]` aspects. Smooth, gear-forgiving, the default
recommendation for a first build on a class. Failure mode: resource starvation,
which reads as "the build feels bad" rather than "the build is weak". Present in
every class.

**Channelled.** A held skill ticking rapidly. Same scaling as core spam plus
channel-specific aspects, and it interacts strongly with Lucky Hit (low
per-tick coefficients — check with `search_skills`). Tends to be tanky-friendly
because you stand still; punished by content that requires repositioning.

**Cooldown / Ultimate cycling.** Damage comes from a handful of long-cooldown
buttons, so the build converts everything into cooldown reduction and resets.
Scales with CDR up to its cap, then with *non-CDR* reset sources — flat refunds,
class mechanics, on-kill and on-crit reductions. Big burst windows, dead time
between them. Strong on bosses where the window can be aligned; weaker on
open-world clear unless the cooldowns come back fast enough.

**Big-hit / Overpower.** One enormous strike rather than sustained output.
Scales with maximum Life and Fortify (Overpower reads off both), Willpower,
guaranteed-Overpower sources, and slam-specific multipliers. Structurally
inverts the usual offense/defense split: stacking Life *is* stacking damage.
Failure mode is relying on the natural Overpower timer instead of guaranteeing
it. Classically Barbarian and Druid, with Necromancer and Paladin variants.

**Damage over time.** Apply a stacking DoT and let it tick. Scales with DoT
totals, DoT duration, application rate up to the stacking ceiling, and
snapshotted buffs at the moment of application (see the damage model). Does
**not** scale with crit by default. Slow to kill single targets but very safe —
you apply and disengage. Strong when the content lets you kite; weak when
enemies die before the DoT matures. Bleed (Barbarian), Burning (Sorcerer,
Barbarian), Poison (Druid, Rogue, Spiritborn), Shadow/Corrupting (Necromancer,
Warlock).

**Minion and companion.** Damage is outsourced to summons — skeletons, demons,
wolves, ravens, golems. Scales with minion-specific damage modifiers, minion
counts and ranks, and the class mechanic that configures them (Book of the Dead,
Warlock Soul Shards, Druid companions). The player invests the freed budget in
survivability. Structural caveats: summons have their own attack pattern and
target acquisition, so single-target damage is inconsistent, and player-facing
damage multipliers usually do **not** apply to them — verify which ones do
before promising scaling. Necromancer, Warlock, Druid, and class-specific
variants elsewhere.

**Deployable / persistent ground effect.** Place a hydra, wall, storm, trap, or
turret and let it work. Scales with duration, count, area, and effect-specific
aspects. Extremely safe playstyle; awkward against mobile bosses. Sorcerer,
Rogue, Necromancer, Druid.

**Projectile / barrage.** Many projectiles, scaling with projectile count,
pierce/ricochet, and per-hit multipliers. Damage concentrates when everything
hits one target, so these are often simultaneously good clear and good bossing —
which is also why they get rebalanced often. Rogue, Sorcerer, Spiritborn,
Necromancer, Paladin.

**Transform / stance.** A form or transformation with its own multiplier and
stat package — shapeshifting, demon forms, angelic transformations. Scales with
form uptime above all: the build's real job is keeping the transformation up.
Druid, Warlock, Paladin, Spiritborn.

**Movement-as-damage.** Evade, dashes, charges, or leaps become the damage
skill via aspects and variants. Scales with movement speed, evade/dash charges
and cooldowns. Fast and slippery; usually low single-target and needs a separate
boss plan. Spiritborn, Rogue, Barbarian.

**Thorns / retaliation.** Damage from being hit or blocking. Scales with Thorns,
block chance, main stat, and retaliation-specific multipliers — a different
affix pool than every other archetype, so gearing barely overlaps. Requires
enemies to actually attack you, which makes it awkward at extreme densities and
against ranged content. Barbarian, Paladin, Spiritborn.

**Aura / support.** Persistent auras buffing the character and the party.
Overlaps heavily with group content; as a solo build it depends on the auras
carrying real multipliers rather than utility. Paladin, Druid.

## Matching the archetype to the request

- **"First character / new to the class"**: core spam or a deployable build.
  Forgiving, few mandatory uniques, works on Codex aspects.
- **"Fast farming / Helltide and Whisper clearing"**: projectile, deployable, or
  movement-as-damage — coverage and speed over single-target.
- **"Bossing / Lair Boss farming"**: cooldown-burst, big-hit, or a DoT that
  matures. Must have a Stagger plan.
- **"Pit pushing"**: sustained damage plus real multiplicative mitigation. Rule
  out archetypes whose damage arrives in windows too narrow for the timer, and
  ones that need enemies to attack them.
- **"Hardcore"**: DoT, minion, deployable, or transform builds that let you
  fight at range and keep an Unstoppable source. Cut greedy offensive nodes for
  the defenses checklist.
- **"Low effort / one button"**: channelled or core spam. Say plainly that
  low-button builds usually give up either clear or bossing.

## Standing warnings

- Never present an archetype as "the best". The class balance patch ships with
  every season and reworks whole subsystems; a pattern that dominated last
  season is routinely retuned.
- Two classes running the "same" archetype are usually not equivalent — the
  class mechanic (Oath, Soul Shard, Specialization, Spirit Hall) changes what
  the pattern can access. Check it before generalizing across classes.
- Seasonal powers frequently favour one or two archetypes hard. A build that
  looks strong this season may be a seasonal-power artefact; say so, and say
  what it looks like without that layer.
- Archetype names here are structural labels, not in-game terms. Always resolve
  them to actual current skills and aspects with `search_skills`,
  `search_affixes`, and `search_uniques` before recommending anything.
