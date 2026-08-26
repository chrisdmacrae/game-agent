---
paths:
  - 'resources/js/components/games/diablo-4/**'
---

# Diablo 4

## ParagonView renders from the build payload; grids are optional enrichment
ParagonView.vue must stay renderable from `definition.paragon` alone — board name, rotation, glyph, notables as cards. The `boards` prop (imported `d4_paragon_boards.grid`) is optional enrichment that upgrades a card to an SVG grid; never make it required.

`GameBuildProfile::treeProps()` sends `paragonBoards` for both games (empty for PoE 2) and only ships grids for the boards the build actually names — a full board table is megabytes of cells the page would never draw.

The SVG rotates the matrix, not the drawing, so cells and labels come out upright; it then crops to the occupied bounding box because the imported grid is a padded 21x21 square. SVG attributes cannot read CSS custom properties, so BOARD_COLORS holds resolved hex — keep it in step with resources/css/byb/colors.css.
