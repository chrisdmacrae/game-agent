---
id: build-anatomy
title: Anatomy of a Complete Build
summary: What a finished, presentable build consists of — the checklist and structure to follow when crafting or reviewing any build.
order: 9
---

A "build" is a complete, coherent specification. If any component below is
missing, the build is not done. Use this as the output template.

## The eight components

1. **Identity**: class + ascendancy + the ascendancy nodes taken (in order of
   acquisition — first two matter most). One sentence on the core concept and
   its win condition ("freeze everything, shatter for AoE clear").
2. **Skill setups**: every skill gem with its support gems. Mark the main damage
   skill. Respect the one-copy support rule and socket-count budget
   (2 default → 5 with Jeweller's). Include the *rotation*: how the skills
   combo in actual play.
3. **Spirit budget**: every persistent effect (auras, heralds, permanent minions,
   meta gems) with its reservation, summed against available spirit.
4. **Passive tree**: keystones and notables by name (verified via
   search_passives), the rough pathing plan, jewel sockets used, and whether
   weapon-set points do anything special. State total points assumed.
5. **Gear**: per-slot stat priorities; specific uniques (verified via get_unique)
   with why; rune choices for resistance gaps. Flag the expensive slots.
6. **Charms + flasks**: which charm(s) and why (usually anti-freeze first).
7. **Defensive summary**: res caps, life/ES pool, mitigation layer, recovery —
   per the defenses model checklist.
8. **Progression plan**: what changes between campaign → early maps → endgame.
   Which gems to use while leveling, what the first uniques to buy are, what the
   "complete" version costs roughly (in exalted/divine terms, using get_prices
   when available — otherwise say pricing wasn't checked).

## Quality gates before presenting

- Run **validate_build** on the final skill/passive/spirit configuration; fix all
  violations, address warnings or explain them.
- Every number quoted (damage, reservation, mod range) traces to a tool response.
- Every named game object (gem, unique, notable) was confirmed to exist in the
  current patch via a query — names hallucinated from PoE1 or old patches are
  the most common failure mode.
- The build has an explicit answer to: "how does it not die?" and "what's the
  main damage button and what makes it big?"

## Presentation format

Lead with a 3-line summary (concept, playstyle, budget tier), then the eight
components as sections. Use tables for gem setups and gear priorities. Keep
leveling advice separate from the endgame target so a new player can follow
stage by stage.
