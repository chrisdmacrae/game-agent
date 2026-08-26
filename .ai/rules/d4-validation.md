---
paths:
  - app/Domain/D4/Validation/D4BuildRules.php
---

# D4 Validation

## Keep D4BuildRules and the D4 build tool schemas in lockstep
The D4 build payload shape is declared twice: D4BuildRules::rules() (request validation, shared by the D4 save_build and validate_build) and D4BuildSchema::properties() (what the MCP client sees — both tools render their schema from it). Change them together or the tools advertise a shape the request rejects. Everything except `equipped_skills` is optional — the MCP writes partial builds and a human finishes them in the web editor.

Where a game cap is volatile (masterworking levels, glyph levels, resistance ceilings, tempered affix count) the rules stay permissive on purpose and D4BuildValidator reports the real limit as a violation or warning, rather than 422-ing a payload the model could still fix.
