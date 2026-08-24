---
id: defenses
title: Defensive Layers and Survival Targets
summary: Resistances, life/energy shield, armour, evasion, block — how each layer works and what numbers a build needs per content tier.
order: 5
---

Builds die to what they didn't layer against. PoE2 characters need multiple
overlapping defensive layers; damage alone is not a build.

## Resistances (the non-negotiable layer)

- Elemental resistances (fire/cold/lightning) cap at **75%** by default; the cap
  can be raised toward **90%** with "+% to maximum X Resistance" sources.
- The campaign applies **resistance penalties** as you progress (roughly −10%
  and then −20% steps at act milestones), so gear must continually re-cap you.
- **Endgame target: all three elemental resistances at the 75% cap.** Below-cap
  resistance is the most common cause of deaths. validate_build warns on this.
- **Chaos resistance** is harder to cap and less mandatory, but deeply negative
  chaos res is a liability in endgame; aim for ≥0% when practical.

## Life and Energy Shield

- **Maximum Life** scales with flat "+to maximum Life" (gear, some tree nodes) and
  "% increased maximum Life" (tree). Unlike PoE1, PoE2's tree has relatively few
  raw life% nodes — gear flat life matters more.
- **Energy Shield (ES)** recharges after not taking damage; int-area tree and
  int-based gear scale it. Hybrid life+ES is common for casters.
- **Chaos Inoculation (keystone)**: life becomes 1, immune to chaos — pure-ES
  builds; requires heavy ES investment before it's safe.
- Rough survivability heuristics (current patch, verify against ladder builds):
  campaign — keep life on every gear slot; early maps — ~1500+ effective pool;
  endgame/pinnacle — 2500+ life or equivalent EHP with mitigation layers.

## Mitigation and avoidance layers

- **Armour** mitigates physical *hits* (better against many small hits; large
  hits punch through). Grants no help vs elemental/chaos unless converted by
  specific passives/items.
- **Evasion** avoids attacks entirely (entropy-based, so streaks are bounded).
  Does nothing against most spells.
- **Block** (shields/some weapons): chance to fully block; can be raised toward
  its own cap. Very strong when stacked deliberately.
- **Stun/armour-break**: heavy hits build stun; stun threshold scales with life.
  Getting stunned in endgame is lethal — some tree/gear grants stun threshold.

## The layering principle

A sound endgame build states, explicitly:

1. Capped elemental resistances (75/75/75), chaos res number
2. Effective pool: life (or ES/hybrid) total
3. At least one mitigation layer (armour / evasion / block / ES recharge) matched
   to the build's playstyle
4. A recovery mechanism: life flask uptime, leech, regen, or ES recharge
5. Crowd-control answers: freeze/stun mitigation for hardcore-leaning builds

When theorycrafting, verify each layer has an actual source (tree node, gear
affix via search_mods, unique via search_uniques) — never hand-wave "get res on
gear" without confirming those slots can roll it.
