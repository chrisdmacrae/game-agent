---
id: paragon
title: Paragon Boards and Glyphs
summary: How paragon points, board attachment and rotation, node tiers, attribute gates, glyph sockets and glyph leveling work — the second half of a character's power.
order: 4
---

Paragon opens at max character level (raised to **70** by the *Lord of Hatred*
expansion) and is where a large share of endgame power lives. It is a grid
puzzle, not a shopping list: the cost of *reaching* a node is usually the real
decision.

## Points

- Paragon levels are earned from experience after max level and grant paragon
  points; a seasonal character also receives a block of paragon points from the
  **Season Rank** track (see the seasons doc). Renown no longer supplies them on
  the seasonal realm.
- Point totals move with expansions and seasonal reward structures. State the
  point budget any presented board plan assumes, and treat the total as
  patch-volatile — check `get_paragon_board` / the data tools rather than
  quoting a number from memory.
- The first stretch of paragon levels comes quickly; the tail is very slow. A
  build plan should therefore be presented as **stages**: what to take at low
  paragon, what the mid-paragon shape is, and what the completed board looks
  like. A guide that only shows the finished 300-point board is not usable.

## Board structure

- Each class has its own set of boards — a fixed **Starting Board** plus a pool
  of additional boards. Only a limited number can be attached at once, so board
  selection is itself a build decision. Verify how many the current patch allows
  with `get_paragon_board`.
- Boards attach through **Board Attachment Gates** at the centre of each edge.
  Reaching a gate costs points and grants a small all-attribute bonus; you then
  choose which board to attach and **which of its four edges** connects.
- Boards can be **rotated in 90° increments**. Rotation changes which nodes sit
  near the entry gate and near the glyph socket, and a good rotation can save
  many points. Any board plan that does not specify orientation is incomplete.
- The consequence: paragon planning is a **shortest-path problem**. Every point
  spent on a filler node to reach a Legendary node is part of that node's price.
  Compare targets by *total points to reach*, not by the node's text alone.

## Node tiers

- **Normal nodes** grant a small fixed attribute bonus. They are the connective
  tissue, but they are never dead — attributes feed main-stat scaling, Armor,
  resistance, Dodge, and Overpower (see the damage model), and they feed the
  rare-node gates below.
- **Magic nodes** grant larger attribute amounts or a small power affix. A ring
  of them surrounds each Rare node. Magic nodes near a glyph socket matter more
  than their text suggests, because glyphs amplify nodes in radius.
- **Rare nodes** give a stat package plus a **gated bonus** that unlocks only
  when you have enough of a named attribute. Crucially, **the threshold rises
  with each additional board attached**, so a rare-node bonus that was trivially
  reachable on board two may be out of budget on board five. Always check gates
  against the *final* board count, not the current one.
- **Legendary nodes** — one per board, thematically matched to it — are the
  reason to take a board at all. They are typically the board's only
  multiplicative (`[x]`) effect, and a board whose Legendary node does not match
  the build is usually not worth attaching for its filler stats.

**Board Rush** is the standard early strategy: path directly toward Legendary
nodes and glyph sockets, taking the cheapest route, and fill in rare-node gates
and magic clusters later once points are plentiful.

## Glyphs

- Each board has **one glyph socket**. A glyph's effect either empowers the
  Normal / Magic / Rare nodes within its **radius** or grants the character a
  direct stat — read which kind it is, because the first kind is worthless
  without allocated nodes in range.
- Socket placement plus board rotation determines how many allocated nodes fall
  inside the radius. Planning a socket means planning the nodes around it.
- **Radius grows at rank breakpoints** and again when the glyph is upgraded to
  **Legendary** quality (a materials cost at the Jeweler), which also adds an
  extra bonus effect. The commonly cited breakpoints are a radius increase in
  the mid-20s and the Legendary upgrade around rank 51 — verify current numbers
  with the data tools before quoting them.
- **Glyphs level exclusively through the Pit.** A completed Pit run grants a
  number of upgrade attempts (more for a deathless clear, more from seasonal
  blessings and War Plans nodes). Success chance depends on the gap between the
  glyph's rank and the Pit tier: running far above your glyph's rank guarantees
  upgrades and can grant several at once. Glyph rank caps well above the useful
  breakpoints, so glyph leveling is effectively an infinite progression sink.
- Practical consequence: **glyph power and Pit-clearing power feed each other.**
  Early paragon advice should be "get to the breakpoints on your two or three
  key glyphs first", not "level everything evenly".

## Presenting a paragon plan

A complete paragon section names, in order of acquisition:

1. Each board, its rotation, and its entry edge
2. The Legendary node that justifies each board
3. The glyph in each socket, its target rank, and the nodes it is meant to
   amplify
4. Which rare-node gates are being taken and the attribute totals that unlock
   them at the final board count
5. A staged plan (early / mid / completed), not just the finished grid

Verify every named node and glyph against `get_paragon_board` and the class
data. Board and node names change between expansions; a hallucinated Legendary
node is the most damaging error you can make in this section, because the whole
route was chosen to reach it.
