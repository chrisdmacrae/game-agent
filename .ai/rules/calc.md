---
paths:
  - 'app/Domain/D4/Calc/**'
---

# Calc

## The D4 calculator reads d4_calc_tables, never the source tree
D4BuildComputer and everything under Calc/ run at request time from the `d4_calc_tables` jsonb rows D4Importer::importCalcTables() persists (attribute_graph, weapon_damage_breakpoints, item_types, level_scaling, class_core_stats, globals, texture_atlases). Never read storage/app/d4-sources at request time. Engine-side numbers the dump does not ship (base life curve, flat-life scaling, core-stat rates) live in Calibration.php with provenance notes — every use must land in the result's `assumptions` list, and ComputedStats::apply()'s precedence (computed never clobbers explicit dps/ehp; `computed.wrote` tracks what the engine owns) is what keeps hand-entered sheet numbers standing across recomputes.
