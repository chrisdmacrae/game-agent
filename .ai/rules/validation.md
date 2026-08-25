---
paths:
  - app/Domain/Poe2/Validation/BuildRules.php
---

# Validation

## Keep BuildRules and SaveBuildTool::schema() in lockstep
The build payload shape is declared twice: BuildRules::rules() (request validation, shared by save_build and validate_build) and SaveBuildTool::schema() / ValidateBuildTool::schema() (what the MCP client sees). Change them together or the tool advertises a shape it rejects. Everything except `skills` is optional — the MCP writes partial builds and a human finishes them in the web editor.
