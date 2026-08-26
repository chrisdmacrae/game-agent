---
id: itemization
title: Itemization and Crafting
summary: Item power and Ancestral gating, affixes and Greater Affixes, aspects and the Codex, uniques and the Mythic quality, tempering, masterworking, transfiguration, runewords and gems.
order: 5
---

D4 gear is a **crafting pipeline**, not a lottery. A dropped item is a starting
point; the finished item is the result of five or six deliberate steps. Any gear
recommendation that stops at "get this affix" is half a recommendation — say
what gets imprinted, tempered, masterworked, and transfigured onto it.

## Item power and Ancestral

- **Item Power** sets the *range* the affixes on an item can roll in. Higher
  item power means higher potential values, nothing else.
- Near max level, ordinary drops cluster at the top of the normal band, so item
  power stops being an interesting variable. The real gate is **Ancestral**:
  Ancestral items drop at a higher item power tier, are unlocked by playing at
  Torment difficulties, and are where Greater Affixes and the top masterworking
  and transfiguration outcomes live.
- Practical consequence: a build's gear plan has two eras — "any legendary with
  the right affix" while progressing through Torment, and "Ancestral with Greater
  Affixes" once farming properly. Say which era each piece of advice is for.

## Affixes

- Rarity determines affix count — Rare items carry fewer affixes than
  Legendaries, and Tempering adds one more slot on top (below).
- **Greater Affixes** are a substantially larger roll of an ordinary affix,
  marked on the item. They can only be **found**, never crafted or enchanted
  into existence, and each one on an item also grants extra tempering charges
  and rerolls. Chasing Greater Affixes on the two or three affixes that actually
  scale the build is the endgame gear grind.
- **Enchanting** (Occultist) rerolls one chosen affix repeatedly for gold and
  materials. It cannot create a Greater Affix. Use it to convert the one dead
  affix on an otherwise good item.
- **What can roll where is fixed per slot.** Never tell a user to get a stat on
  a slot without checking `search_affixes` for that slot first — this is the
  most common fabrication in gear advice.

## Legendary Aspects and the Codex of Power

- Every Legendary item carries one **Aspect**. Salvaging the item at the
  Blacksmith extracts the Aspect permanently into the **Codex of Power**, and
  salvaging a higher-rolled copy **upgrades** the stored version.
- Aspects are imprinted at the Occultist onto a Rare or Legendary item (an
  imprinted Rare becomes Legendary). Aspects belong to categories — Offensive,
  Defensive, Resource, Utility, Mobility — and each category is restricted to
  certain slots, so the same aspect cannot go anywhere you like.
- Aspects are where most of a build's `[x]` multipliers come from. Building a
  character is largely **deciding which aspects must be equipped, then working
  out which slots can hold them** — do this *before* choosing uniques, because
  every unique equipped is a slot that cannot hold an aspect.
- The Codex resets on a new seasonal character; on Eternal it is permanent.

## Uniques and the Mythic quality

- **Uniques** have a fixed affix set plus a **Unique Power** that no aspect can
  replicate. Their cost is the aspect slot they occupy — always justify a unique
  by what its Unique Power enables, not by its stat lines.
- **Mythic** is now an item **quality** rather than a separate rarity: any
  Unique can be raised to Mythic. A Mythic item is always Ancestral, has its
  Unique Power boosted, and rolls its other values at maximum. This reworking
  (Season 14's "Mythic Uniques 3.0") replaced the older fixed list of Mythic
  items — advice written before it is wrong.
- Mythic items are crafted at the **Horadric Cube** with seasonal fragments, or
  at the Jeweler with runes and Resplendent Sparks, and require max level and
  Torment difficulty. There is a limit on how many **crafted** Mythics may be
  equipped simultaneously, while dropped Mythics are not limited — check the
  current rule before designing a build around several.
- Verify every unique with `search_uniques` before naming it. Uniques are added,
  reworked, and retired at every expansion.

## Tempering

Tempering adds one extra affix to a Rare, Legendary, Unique, or Mythic item at
the Blacksmith.

- Recipes are learned permanently from **Tempering Manuals** found in the world.
  Each manual is a **family** of related affixes — you choose the manual, the
  game chooses which affix inside it you get, and rolls its value.
- Manuals are grouped into categories (Weapon, Offensive, Defensive, Utility,
  Mobility, Resource) and each category is only usable on certain slots.
- Items have a limited number of **tempering charges**, extended by Greater
  Affixes on the item. Rerolling costs a charge.
- **Items can no longer be permanently bricked.** When charges run out, a
  **Scroll of Restoration** refills them. Advice from the Loot Reborn era about
  "bricking" items is out of date — say so if a user repeats it.

Tempering is where builds get the affixes that make a specific skill work (extra
projectiles, guaranteed effects, skill-specific damage). Treat the temper choice
as part of the build spec, not an afterthought.

## Masterworking

At the Blacksmith, items gain **Quality ranks** (currently up to 25), each rank
adding a small percentage to the item's base stats **and every affix, tempering
affix included**. At the final rank an item gains a **Capstone bonus**: a large
boost to one randomly chosen affix, which can be **rerolled** repeatedly at a
materials cost to land on the affix you actually want.

- Materials (Obducite and its higher-tier counterparts) come from the Pit,
  Nightmare Dungeon strongrooms, Undercity tributes, and Infernal Hordes.
- Two-handed weapons cost roughly double.
- Priority order is nearly always: weapon first (it multiplies everything),
  then the pieces carrying the build's key affixes.
- Because masterworking multiplies affixes, it interacts with Greater Affixes:
  a Greater Affix that also catches the Capstone is the single largest stat
  outcome available on a piece of gear.

## Transfiguration

The Horadric Cube's **Transfigure** step is the last operation on a finished
item — after tempering, masterworking, enchanting, socketing, and imprinting.
It applies a random powerful modification (extra quality, an added Greater
Affix, an extra bonus stat, and other outcomes), consumes seasonal reagents, and
can be steered or protected with **Tuning Prisms**. It is irreversible.

Because it comes last, transfiguration changes the *sequence* of gear advice:
finish an item completely before transfiguring it, and do not transfigure a
placeholder.

## Sockets, gems, and runewords

- Gems slot into sockets for straightforward stat bonuses (offensive in weapons,
  defensive in armor, utility in jewelry).
- **Runewords** are built from two runes in a **two-socket item** (helm, chest,
  pants, and two-handed weapons): a **Rune of Ritual** (the cause — a condition
  you meet in combat, which generates Offering) plus a **Rune of Invocation**
  (the effect — triggered once enough Offering accumulates). Effects range from
  casting a skill you do not have, to granting skill ranks, to restoring
  resource or life.
- You cannot pair two Ritual runes or two Invocation runes, and a character can
  carry only a limited number of runewords at once. Feeding **more** Offering
  than required can trigger **Overflow** bonuses on some Invocation runes, so
  Ritual-rune generation rate is a real tuning variable.
- Runewords frequently cover a build's structural gap — an Unstoppable source, a
  skill the class cannot otherwise access, resource sustain. Consider them when
  a build has a hole rather than a stat shortfall.

## Presenting gear

Per slot, state: the item (rare/legendary/unique/mythic), the affix priorities
in order, the aspect imprinted, the tempering manual chosen, the masterworking
Capstone target, and whether the slot carries a socket/runeword. Flag which one
or two slots are the expensive chase pieces and what the budget stand-in is.
Every named affix, aspect, and unique must be confirmed with a tool call.
