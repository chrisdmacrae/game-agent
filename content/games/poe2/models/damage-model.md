---
id: damage-model
title: Damage Model (qualitative)
summary: How hits are computed — damage types, conversion, crit, attack vs spell scaling, DoT — described qualitatively. This toolkit does not simulate DPS.
order: 7
---

**Scope note**: this toolkit deliberately does NOT compute DPS. Use this model to
reason about *what scales what*; present relative comparisons, not invented
numbers. For exact figures, users should consult Path of Building (community
PoE2 fork).

## Damage types and sources

- Five damage types: **physical, fire, cold, lightning, chaos**.
- **Attacks** scale with weapon damage (weapon base × attack modifiers) plus flat
  added damage; weapon choice is a primary scaling axis. Attack time comes from
  the weapon.
- **Spells** have their own base damage per gem level (see get_gem per-level
  stats) — gem level is a primary scaling axis ("+X to Level of Spell Skills" on
  gear/amulets is premium caster scaling; verify slots with search_mods). Cast
  time is per-skill.
- **Minions** are separate actors: they use their own stats; "minion damage"
  modifiers and minion gem levels scale them. Player damage mods do NOT apply.

## Conversion

Damage can be converted between types (e.g. "50% of Physical converted to Cold").
In PoE2, conversion happens **before** damage modifiers apply — the damage is
then scaled only by modifiers matching its **final** type (no PoE1-style
double-dipping). Order of conversion follows the standard chain
(physical → lightning → cold → fire → chaos). Treat intricate conversion stacking
as advanced and verify wording carefully.

## Critical hits

- Skills/weapons have a base critical hit chance; "increased Critical Hit Chance"
  scales it multiplicatively off that base.
- Critical hits deal a **Critical Damage Bonus** (base +100%, i.e. double damage),
  scaled further by "+% Critical Damage Bonus" modifiers.
- Crit builds want both chance (to a reliable threshold) and bonus; non-crit
  builds can ignore crit lines entirely ("Resolute Technique"-style tradeoffs may
  exist — verify on the tree with search_passives).

## Speed, area, projectiles

- Attack/cast speed multiplies DPS but not per-hit damage (ailment builds often
  prefer big slow hits).
- "Increased Area of Effect" scales radius-derived area; more projectiles usually
  means more coverage, with per-target damage unchanged unless multiple hit.
- PoE2 combat is combo-oriented: many skills have setup/payoff pairings
  (e.g. armour-break then payoff, freeze then shatter, curse then damage).
  A build's "rotation" matters — single-button builds exist but are the
  exception early in a league.

## Comparing options honestly

When choosing between supports/passives, compare using the modifier algebra:
a "40% more" support beats "60% increased" if the build already has 200%+
increased from tree. Say *why* an option wins in algebra terms; if magnitudes
are close, say it's close and note the toolkit doesn't simulate.
