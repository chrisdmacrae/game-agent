---
id: skill-system
title: Skills, Skill Tree, and Class Mechanics
summary: The skill tree's clusters and level gates, ranks and +Rank sources, the modifier/variant choice structure, the six-skill bar, per-class resources, and each class's unique mechanic system.
order: 1
---

A D4 character has three separate skill-shaped decisions: which **six skills**
go on the bar, how the **skill tree** is spent, and how the **class mechanic**
is configured. All three are respecified freely (refunds are free), so a build
document should present the endgame target and the leveling path separately.

## The bar

Six active slots, plus the Evade dash. Every build must fit into six: typically
a generator, one or two spenders, one or two buffs/utility, a defensive/mobility
skill, and often an Ultimate. If a proposed build needs seven buttons, it is not
a build yet — cut something and say what.

## Tree structure

The tree is a chain of **clusters** that unlock at fixed character levels, each
gated behind spending points in the previous one:

1. **Basic** (from level 1) — cheap resource generators
2. **Core** (level 3) — the resource spenders that are usually the main damage
3–5. **Three class-specific clusters** (roughly levels 4, 8, and 13) holding
   defensive, mobility, utility, and class-flavour skills plus their passives
6. **Ultimate** (around level 19) — long-cooldown Ultimates and the
   class-defining capstone passives

Levels shift between patches; confirm the current gates with `search_skills`
rather than quoting these. Passive nodes sit alongside the actives in each
cluster and have their own point requirements.

**Point budget**: roughly one point per character level plus a block of extra
points awarded through the Season Journey / Season Rank. The exact total moves
with the level cap (raised from 60 to 70 in the *Lord of Hatred* expansion) and
with seasonal reward structure — state the assumed total for any tree you
present, and treat the number as patch-volatile.

## Ranks

- Each active skill accepts multiple ranks from the tree (up to **rank 15**
  since the *Lord of Hatred* tree rework — previously far fewer). Each rank adds
  a roughly fixed percentage of the skill's rank-1 power, so ranks are **linear**
  and land in the additive bucket's neighbourhood: heavy ranking is a real but
  diminishing investment, not a multiplier.
- **Extra ranks from gear and paragon** ("+X Ranks of Core Skills", "+X to
  \[skill\]") stack on top of tree ranks and can push a skill past the tree
  maximum. They generally require at least one point invested in the skill to do
  anything, and they do **not** count toward cluster unlock requirements.
- Because ranks are additive-ish, +Ranks affixes compete with `[x]` sources.
  Prefer +Ranks when they are cheap and the build is rank-starved; prefer
  aspects/uniques with `[x]` when the additive pile is already large.

## Modifiers and Variants (the choice structure)

Since the *Lord of Hatred* rework, every active skill has a standardized branch
layout:

- **Modifier branches** — two pairs of mutually exclusive round nodes. You take
  one from each pair. These are the small, always-relevant tweaks (extra damage
  under a condition, a resource refund, a CC application).
- **Variant nodes** — diamond-shaped, mutually exclusive, one only. Variants
  come in three flavours: **Primary** (straightforward amplification of the base
  ability), **Utility** (quality-of-life, targeting, mobility), and
  **Transformative** (reworks the skill outright — element conversion, changed
  delivery, changed scaling). Base-game players see two of the three; expansion
  owners get access to all three.

Transformative variants are where builds actually diverge — a skill's identity,
damage type, and which aspects work with it can all hinge on one diamond node.
When describing a build, **always name the chosen variant and both modifier
picks**; a skill name alone does not specify the build. Verify current names and
effects with `search_skills` — this layer was rewritten wholesale and older
guides use the retired "Enhanced / Upgrade" vocabulary.

## Cooldowns and resources

- **Cooldown Reduction** is hard-capped and comes from gear, paragon, aspects,
  and on-hit/on-crit reduction effects. Cooldown-driven builds care about the
  cap and about non-CDR reduction sources (flat cooldown refunds, resets from
  Ultimates or class mechanics) that bypass it.
- **Resource Cost Reduction** stacks multiplicatively and never reaches zero.
- Each class's resource behaves differently, and the difference dictates the
  rotation more than the numbers do:

| Class | Resource | Behaviour |
| --- | --- | --- |
| Barbarian | Fury | Built in combat, **decays out of combat** |
| Druid | Spirit | Generators; passive regen only in **Human form** |
| Necromancer | Essence | Generators; several skills also spend Life |
| Paladin | Faith | Generators plus a slow passive regen; some nodes shift costs onto Life |
| Rogue | Energy | Regenerates on its own; Core skills are expensive |
| Sorcerer | Mana | Pure regeneration; mana starvation is the class's defining constraint |
| Spiritborn | Vigor | **No passive regen** — only basic attacks refill it |
| Warlock | Wrath + Dominance | Two pools: Wrath for spells/Core, Dominance for Greater Demon skills (slow regen, few generation sources) |

A build that spends more than it generates is not a build. State the generator
and the sustain plan explicitly, and check resource affixes with
`search_affixes` before assuming a slot can fix a deficit.

## Class mechanics (the second tree)

Each class has a separate system, unlocked by a class quest around level 15 (with
exceptions noted), that is as build-defining as the tree itself. **Never present
a build without specifying its class-mechanic configuration.**

- **Barbarian — Arsenal System.** Four weapon slots (two two-handers plus
  dual-wielded one-handers); each skill is assigned to a weapon. **Weapon
  Expertise** ranks up per weapon type with a bonus at max rank, and a separate
  **Technique slot** grants one Expertise bonus regardless of what is equipped.
- **Druid — Spirit Boons.** Four spirit animals (Deer, Eagle, Wolf, Snake), one
  boon taken from each, plus a **bond** with one spirit that grants a second boon
  from it. Unlocked by collecting offerings, so it ramps through leveling.
  Shapeshift forms (Werebear / Werewolf / Human) each carry baked-in defensive
  and speed bonuses — form uptime is a defensive stat for Druids.
- **Necromancer — Book of the Dead.** Unlocks unusually early (around level 5;
  the Golem later). Three minion categories, three variants each, and each
  category can instead be **sacrificed** permanently for a passive bonus — the
  fundamental "minion build vs. sacrifice build" fork.
- **Paladin — Oaths.** Four Oaths (Zealot, Juggernaut, Judicator, Disciple), one
  active at a time; unlocked automatically with **no class quest**. The Oath
  gates which keyword mechanics your build can use (Fervor echoes, Resolve
  stacks, Judgement marks, Retribution thorns, the Arbiter transform), so the
  Oath choice comes *before* skill selection.
- **Rogue — Specialization.** Three mutually exclusive options unlocked in
  sequence: **Combo Points** (Basic skills charge Core skills), **Inner Sight**
  (marked kills grant a free-resource window), and **Preparation** (spending
  resource cuts Ultimate cooldown; the Ultimate resets other cooldowns).
  Separately, **Imbuements** apply Cold/Poison/Shadow to the next attacks on a
  cooldown, and the Rogue equips melee and ranged weapons simultaneously.
- **Sorcerer — Enchantment slots.** Two slots (second unlocked later). A skill
  placed in a slot grants a passive version of its effect while remaining usable
  on the bar; invested ranks and the chosen modifiers/variant carry into the
  enchantment. Ultimates cannot be enchanted, and the skill needs at least one
  point in it.
- **Spiritborn — Spirit Hall.** Two slots (Primary and Secondary; the same
  spirit may occupy both). Four guardians — Jaguar (attack speed/fire), Eagle
  (mobility/lightning/crit), Gorilla (damage reduction/Thorns/barrier/Resolve),
  Centipede (poison DoT/weaken/healing). Restricted to two-handed Glaives and
  Quarterstaves.
- **Warlock — Soul Shards.** Four Shards (Legion, Vanguard, Mastermind,
  Ritualist), each with a set of **Fragments** unlocked later, giving many
  configurations. The Shard binds a Greater Demon and reshapes which skills,
  aspects, and stats matter — the heaviest class-mechanic choice in the game.

Names, unlock levels, and slot counts in this list drift with patches. Treat the
*shape* as durable and verify the specifics with `search_skills` or the class
data tools before asserting them to a user.
