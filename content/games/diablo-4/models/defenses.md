---
id: defenses
title: Defensive Layers and Survival
summary: Armor, resistances, damage reduction stacking, Life, Barrier, Fortify, Dodge, crowd control and Unstoppable — how each layer works and what a build must state to be survivable.
order: 3
---

Damage does not carry a build past the mid Torment tiers; deaths do. A D4 build
is only complete when it can answer "what stops the one-shot?" with named
sources. Toughness — the single number the character sheet shows — is an average
and hides gaps, so never treat it as the answer.

## Armor (not what other ARPGs taught you)

**Armor reduces ALL incoming damage, not just physical.** This is the single
most important defensive fact in D4 and the one transferred intuition gets
wrong most often.

- Mitigation follows a diminishing curve against a level-scaled constant and
  approaches a hard cap; the constant rises with character level, so the same
  Armor value is worth less at max level than it was while leveling. Armor
  requirements go **up** as you level, not down.
- Armor comes in two layers: a **flat/additive** layer (item implicits, Armor
  affixes, Strength conversion) and a **multiplicative `% Armor`** layer
  (aspects, paragon nodes, auras, buffs). The percentage layer multiplies the
  flat layer, so a build with a large flat base gets much more out of `% Armor`
  sources than one without.
- There is a practical Armor "cap" target where further Armor stops paying;
  builds should state that they hit the current cap and then stop investing. The
  exact number is level- and patch-dependent — direct users to a planner rather
  than quoting one.

## Resistances

Gear rolls resistances for the non-physical damage types — **Fire, Cold,
Lightning, Poison, Shadow** (Physical also exists as a damage type but is
covered primarily by Armor and has far fewer resistance sources).

- Resistance uses the *same shape* of curve as Armor against its own smaller
  constant, and also approaches a cap. Because the constant is smaller,
  resistance reaches useful values with far less raw stat than Armor does.
- Same two-layer structure: flat resistance (implicits, affixes, gems,
  Intelligence conversion) then `% Resistance` multipliers.
- **All Resistance** sources — gems in armor slots, Intelligence, "to All
  Resistances" affixes — are the efficient way to close five gaps at once.
- A resistance sitting far below the others is the actual death cause. Verify
  each element individually; do not accept the averaged Toughness number.

Use `search_affixes` to confirm which slots roll resistance and at what tiers
before telling a user to "get resistance on gear".

## Damage reduction and how layers combine

Distinct Damage Reduction sources — `% Damage Reduction`, `DR from Close`,
`DR while Fortified`, `DR from Distant`, class DR buffs — **multiply** with each
other and with Armor and resistance:

    total taken = (1−DR1) × (1−DR2) × … × (1−armorDR) × (1−resDR)

Two consequences that should shape every recommendation:

1. **One large DR source beats several small ones.** A single 50% source is
   worth more than five 10% sources.
2. **Conditional DR is only worth its uptime.** "DR while Fortified" is a
   full-time stat if the build generates Fortify constantly and near-worthless
   if it does not. Always name the generator.

Debuffs count as defense too: **Weaken** reduces the damage enemies deal, and
scales down against Elites and Bosses. Crowd control that stops an attack from
happening is the cheapest mitigation there is.

## The health pool: Life, Barrier, Fortify

- **Maximum Life** = (base life for your level + flat `+Life` from gear/paragon)
  × `% Increased Life`. Flat Life on gear is the backbone; `% Life` multiplies
  it, so the two want to be built together.
- **Barrier** is a temporary second health bar that absorbs damage before Life,
  expires on a timer, and is capped relative to Maximum Life. Barrier generation
  is a *recovery* mechanism as much as a defensive one, and several aspects
  scale damage off having a Barrier active — check whether a build's barrier is
  load-bearing offensively before cutting it.
- **Fortify** is a parallel pool built up by skills, aspects, and passives. While
  your Fortify is at or above your current Life you are **Fortified**, which
  grants flat damage reduction; incoming damage consumes Fortify first. Fortify
  is a *generation-rate* stat, not a stockpile — a build needs continuous
  generation to stay Fortified through a fight, and many classes tie extra
  damage reduction or healing to being Fortified. It also feeds Overpower damage
  (see the damage model), which is why Fortify builds are often simultaneously
  the tankiest and hardest-hitting.

## Avoidance

- **Dodge** avoids the hit entirely. Sources multiply together; Dexterity adds a
  small flat amount. Dodge is unreliable as a primary layer — it does not stop
  the specific hit that kills you — but pairs well with on-dodge aspects.
- **Evade** (the dash) is the real avoidance layer: positioning and the evade
  charge system, not a stat.

## Crowd control, Unstoppable, and Stagger

- Player-facing: being crowd-controlled at high Torment is usually fatal because
  it removes your ability to react. **Unstoppable** grants immunity to control
  effects and breaks you out of them; every serious build needs an Unstoppable
  source — a skill, an aspect, a rune, or an elixir — and must say which.
- Monster-facing: repeatedly hard-CCing a normal or elite enemy eventually makes
  it Unstoppable for a period, so infinite lockdown is not a strategy against
  trash indefinitely.
- **Bosses are immune to crowd control** and instead accumulate **Stagger** from
  CC effects. Stagger builds fastest from *varied* CC types rather than spamming
  one; after a Stagger the threshold rises sharply for a window, and each extra
  party member raises it further. A boss-killing build that relies on Stagger
  windows must carry multiple distinct CC types.

## Class-specific defensive mechanics

Several classes have their own layer — Spiritborn's stacking Resolve (armor,
consumed on hit), Druid's Werebear form bonuses, Necromancer minions as damage
sponges, Paladin block and Resolve, Barbarian's raw Strength-to-Armor. These are
not interchangeable; verify with `search_skills` and the class mechanic section
of the skill-system doc rather than assuming a generic answer.

## The checklist a build must satisfy

A finished endgame build states, explicitly:

1. Armor at (or deliberately near) the current cap for its level
2. All five non-physical resistances individually at target, not averaged
3. Maximum Life total, and whether `% Life` sources are actually multiplying a
   real flat base
4. At least one large multiplicative DR source with stated uptime
5. A recovery mechanism: Life on Hit / healing, Barrier generation, Fortify
   generation, or potion uptime
6. An **Unstoppable** source
7. For bossing: a Stagger plan, or an answer for why it does not need one

If any line is missing, the build is not endgame-ready — say so plainly rather
than presenting it as finished.
