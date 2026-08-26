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
