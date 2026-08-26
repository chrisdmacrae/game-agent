---
paths:
  - 'app/Domain/D4/Import/**'
---

# Import

## d4data reference traps the importer already works around
The DiabloTools/d4data dump has four joins that look obvious and are not. Go through SnoRefs/StringResolver rather than re-deriving them:

- `DT_GBID.group` is an **eGameBalanceType id, not an SNO group**, and one type can map to several `.gam` sheets (43 -> SkillTreeRewards *and* Warplans_SkillTreeRewards). `SnoRefs::gameBalanceRow()` handles both, plus the sheets whose `ptData` is `[null]`.
- Text joins by **filename convention**, not by any id: `base/meta/<Group>/<Name>.<ext>` -> `enUS_Text/meta/StringList/<Group>_<Name>.stl.json`. Label casing differs per group (`name`/`desc` vs `Name`/`Desc`), so StringResolver keys everything lowercased. Aspect, PlayerClass, SkillKit and most Tempered_* affixes have no StringList at all — fall back (aspects read their Affix's text; affixes without one generate from AttributeDescriptions.stl.json keyed on `__eAttribute_name__`).
- Every 8-wide class mask (`fUsableByClass`, `fAllowedForPlayerClass`, `arUsableByClass`) is ordered Sorcerer, Druid, Barbarian, Rogue, Necromancer, Spiritborn, Paladin, Warlock. Eight, not five or six.
- Skill category comes from `tPrimaryTag.gbidSkillTag.name` (`Skill_Primary_Core`). `eSkillCat` is class-relative and meaningless across classes.

No enum in the dump has a published name map except `eAttribute`, so any enum -> string mapping in D4Importer is inferred and documented in place. Content is never dropped for being unreleased — ContentFilter only sets `is_released`.

## Formula and tooltip token semantics
Verified against the dump; do not re-derive.

- `SF_n` is a **0-based position** in that Power's `ptScriptFormulas[]`, not an id. Blank entries hold their slot. Formulas reference each other (`SF_0 / SF_3`), so FormulaEvaluator resolves them recursively with a cycle guard.
- `Table(n, sLevel)` = `GameBalance/PowerFormulaTables.gam.json` -> `ptData[0].tEntries[n].flValue[sLevel]`, **positional both ways** (row 34 is SkillRankBonus). `sLevel` is the skill rank and indexes the 151-float array directly.
- Display syntax is `[expr]` or `[expr|flags|]`. The flags are an unordered **bag of characters**, not a fixed set — `%x`, `x%`, `1%x`, `x1%` all occur: digit = decimal places (default 0), `%` = append a percent (the expr already scaled by 100), `x` = multiplicative (rendered `12%[x]`), `+` = force a sign, `~` = rounded.
- Evaluation is **interval** arithmetic, because rolls are ranges. `RandomInt(a,b)`, `FloatRandomRangeWithInterval(steps,lo,hi)`, `FloatRandomRangeWithIntervalUniqueAffixPityBonus(steps,lo,hi)` and `FloatRangeWithIntervalUniqueAffixPityBonus(roll,steps,lo,hi)` all mean "the last two args are the range" — inferred from the names, there is no published map. `IPower()` reads the item power.
- `CurrentLegendaryRank()` is deliberately **not** implemented: aspect legendary ranks are not derivable from the dump (`arMaxLegendaryRanks` is a 6-wide unnamed mask), so those ~620 affixes keep a null min/max and their token stays visible rather than being guessed.
- Affix min/max come from the **highest** `arRanges` item-power breakpoint that evaluates, with that breakpoint stored as `value_range.item_power`.
- Nothing here may throw: an unparseable formula returns null and the token survives into the rendered text. Never let a rendered string show a number the data did not produce.

## Icon handles resolve through Texture atlases; keep the sparse patterns in sync
D4 icon handles (hIconNormal, hIconOverride, tInvImages, hIconMask, legendaryNodeIcon) are 32-bit keys into Texture/*.tex.json ptFrame[].hImageHandle — not SNO ids, not hashes. TextureFrames resolves them to {texture sno, frame, fractional UVs}; the sparse checkout is no-cone with patterns from D4DataSource::sparsePatterns() (Texture/2DUI_* + 2DInventory_* only — the full group is 142k files), mirrored by hand in .github/workflows/d4-data-artifact.yml. After changing icon sources run `php artisan d4:verify-icons`; known-unresolvable entities go in its KNOWN_MISSING list. Pixels come from tools/d4-icon-extractor on a Windows box with the game installed; missing sheets degrade to letter badges.
