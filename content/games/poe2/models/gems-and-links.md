---
id: gems-and-links
title: Gems, Supports, and Linking Rules
summary: PoE2's skill gem system — uncut gems, per-skill support sockets, the one-copy-per-support rule, and attribute requirements. Very different from PoE1.
order: 2
---

PoE2's gem system is fundamentally different from PoE1. Skills are NOT socketed in
gear, and "6-links" are not gear-dependent.

## Skill gems

- Skills come from **skill gems** engraved from **Uncut Skill Gems** (drop-based,
  tiered by gem level). You choose which skill an uncut gem becomes.
- A character has a skill panel with a limited number of skill slots (9 by default
  in the current patch; more via rare "+1 skill slot" sources).
- Gems have **level requirements** and **attribute requirements** (str/dex/int,
  see `requirement_weights` in get_gem). You need enough of the attribute to use
  the gem — plan tree/gear attributes accordingly.
- Gem quality exists (via Gemcutter's Prisms) and adds a bonus specific to each gem.

## Support gems — the linking rules

- **Every active skill gem has its own support sockets**: 2 by default, expandable
  to 3/4/5 using **Jeweller's Orbs** (Lesser → Greater → Perfect). A "5-link" is a
  currency investment per skill, not a gear drop.
- **THE ONE-COPY RULE (hard rule)**: each support gem may be socketed into only
  ONE skill across the entire character. Two skills cannot both use e.g. Martial
  Tempo. This forces diversification of supports across your skill setups —
  validate_build enforces it.
- Supports have **allowed/excluded skill types** (see get_supports_for_gem). A melee
  support cannot go on a spell, etc.
- Supports generally modify behavior or grant **more/less multipliers**; in PoE2
  most damage supports avoid flat "mana multipliers" (PoE1-style), but many trade
  a drawback — read the support's stat text.
- Spirit gems (persistent buffs, auras, meta gems) also accept supports.

## Meta gems

Meta gems (e.g. Cast on Shock, Cast on Ignite, Invoke-style triggers) are **spirit
gems that trigger OTHER socketed skills**: you socket a skill gem inside the meta
gem. They reserve spirit and build energy from their trigger condition. Trigger
rates were heavily rebalanced during Early Access — verify current numbers with
get_gem rather than assuming pre-0.2 behavior.

## Practical crafting workflow

1. Pick the main damage skill; check its types with get_gem.
2. Use get_supports_for_gem to shortlist compatible supports; the game's own
   recommendations are flagged `is_recommended` but are not always optimal.
3. Assign supports greedily to the MAIN skill first (best more-multipliers), then
   give secondary skills the leftovers (one-copy rule).
4. Check attribute requirements across all chosen gems — off-attribute gems need
   attribute investment from tree/gear.
5. Budget Jeweller's Orbs: a league-start build should assume 2-3 sockets per
   skill, not 5.
