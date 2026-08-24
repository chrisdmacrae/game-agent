---
id: itemization
title: Itemization, Affixes, and Crafting
summary: How gear works — bases, prefixes/suffixes and tiers, runes, uniques, charms, and the crafting currency loop. Use search_mods to verify what can roll where.
order: 8
---

## Item anatomy

- A rare item = **base type** (implicit + defense/damage stats + attribute
  requirements) + up to **3 prefixes and 3 suffixes** from the affix pool.
- Affixes come in **tiers** (higher tier = bigger numbers, higher item level
  required). search_mods reports tier counts and best-tier ranges per mod family.
- **What can roll where is fixed by item class tags** — e.g. "+to Level of Spell
  Skills" only on certain slots, spirit only on amulet/body armour (verify with
  search_mods item_tag filters; NEVER assume a slot can roll a mod).
- **Gear sockets** hold **Runes** (and soul cores): flat, swappable bonuses like
  resistances or damage. Budget builds fix resistance gaps with runes.
- **Charms** sit in belt slots and trigger automatically against matching
  threats (freeze, stun, ignite...). They replaced most of PoE1's utility flasks;
  you keep one life and one mana flask.

## Uniques

Unique items have fixed named mod sets with rolled ranges (search_uniques /
get_unique — the data includes legacy variants; the `current_mods` list is what
drops now). Uniques are build enablers or budget stopgaps; rares outscale most
uniques in raw stats late. When recommending a unique, state WHY the fixed mods
beat a rare in that slot for this build.

## The crafting loop (Early Access state)

Crafting is currency-driven and less deterministic than late PoE1:

- **Transmutation/Augmentation** magic → **Regal** to rare → **Exalted Orbs** add
  affixes → **Chaos Orb** swaps one affix → **Divine** rerolls numeric values.
- **Essences** guarantee one specific affix when upgrading.
- **Omens** (league mechanics permitting) steer outcomes.
- Practical league-start guidance: buy key rares from trade, craft only
  incrementally (transmute+aug+regal on good bases, exalt when brave). Deep
  deterministic crafting mostly isn't available — don't promise mirror-tier gear.

## Stat priorities per slot (framework)

For each gear slot, a build guide should state priorities in order, e.g.:

- Weapon/sceptre: the build's primary scaling stat(s)
- Body/helm/gloves/boots: life, resistances, then build-specific (ES%, minion
  levels, attack speed...), movement speed on boots is near-mandatory
- Rings/amulet/belt: resistance fixing, life, then offense (amulets carry the
  premium offensive affixes like +skill levels or spirit)

Always sanity-check each named priority against search_mods so the stated affix
actually exists on that slot at a reachable tier.
