---
id: passive-tree
title: Passive Tree and Weapon-Set Points
summary: The shared passive tree, keystones/notables, jewel sockets, attribute travel nodes, and PoE2's weapon-set-specific point allocation.
order: 4
---

All classes share one large passive tree; your class determines the starting
location. Ascendancy classes add a separate small tree (see get_ascendancy).

## Node kinds

- **Small nodes**: minor stats, mostly travel. Many travel nodes are **generic
  attribute nodes** where you choose which attribute (+str/dex/int) they grant —
  PoE2's main lever for meeting gem attribute requirements.
- **Notables**: named nodes with meaningful stat packages (search_passives
  kind=notable). The backbone of tree planning.
- **Keystones**: build-defining tradeoffs (e.g. Chaos Inoculation, Eldritch
  Battery-style effects). Always read the downside literally — they are worded
  exactly (search_passives kind=keystone).
- **Jewel sockets**: hold jewels with rolled or unique effects (including
  time-lost jewels that modify nearby passives).

## Point budget

- ~1 skill point per level, plus quest rewards through the campaign. As a working
  heuristic, a level-90 character has roughly **110 points** to spend
  (validate_build uses level−1+24; exact quest totals shift between patches).
- **Ascendancy points**: 8 total, earned 2 at a time from ascension trials
  (Trial of the Sekhemas, Trial of Chaos, and later trial content).

## Weapon-set points (PoE2-specific)

Characters have **two weapon sets** and can bind skills to a set (auto-swapping
when the skill is used). The tree supports **weapon-set-specific allocation**:
a number of your points can be assigned per weapon set — those nodes are only
active while the matching set is equipped. A PoE2 tree is therefore effectively
three layers:

1. Shared allocation (always active)
2. Weapon Set I-only nodes
3. Weapon Set II-only nodes

Most builds ignore this and play one set; advanced builds use set II for a buff/
utility skill with different scaling. When presenting a build, state whether
weapon-set points are used and for what.

## Allocation rules (HARD CONSTRAINT)

The game engine only allows allocating nodes **contiguously**: every allocated
node must connect to your class start through other allocated nodes. You cannot
"cherry-pick" a distant notable without paying for every travel node on the way.
validate_build enforces this on `passives.node_ids`.

Three mechanics are exceptions — they allocate nodes WITHOUT pathing, and must
be declared in `passives.granted_nodes` with their source:

1. **Instilled amulets** (`instilled_amulet`): applying three Distilled
   Emotions to an amulet grants one chosen **notable** (notables only — never
   keystones or smalls) while the amulet is worn.
2. **Unique jewels** (`unique_jewel`): certain jewels (e.g. **From Nothing**)
   allocate specific nodes or nearby clusters from a socketed jewel, with no
   pathing required.
3. **Ascendancy mechanics** (`ascendancy_mechanic`): some ascendancy passives
   explicitly allow detached allocation (e.g. the Oracle's **Entwined
   Realities** interacting with keystones).

When planning a tree: pick target notables/keystones, then route real travel
paths between them and the class start, counting every node. A distant notable
is usually better obtained via an instilled amulet than a 10-point detour —
compare the point cost honestly.

## Planning heuristics

- Efficiency = stats per point. Travel to a cluster only if the notables justify
  the path cost; 3+ travel points for one mediocre notable is usually a loss.
- Life/defense nodes are not optional for most builds — a pure-damage tree dies
  in endgame content (see the defenses model).
- Attribute requirements: check every gem's requirements and route through
  choosable attribute nodes to fix shortfalls before adding luxury damage.
- Jewel sockets scale late: their value depends on jewels you can afford.
- Use search_passives to verify a notable/keystone actually exists in the current
  patch with the wording you expect — nodes get renamed and rebalanced between
  Early Access patches.
