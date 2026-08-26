---
paths:
  - app/Http/Requests/BuildUpdateRequest.php
---

# Requests

## The web editor validates with the game's own rules, same as its MCP tool
BuildUpdateRequest merges `$this->profile()->rules('build.')` rather than restating the payload shape, and the controller runs the payload through the same profile's `normalize()` and `validator()` exactly like that game's SaveBuildTool does. The profile comes from the route's `{game:slug}` binding — see .ai/rules/domain-builds.md — so `/poe2/build/x` validates with `BuildRules` and `/diablo-4/build/x` with `D4BuildRules`. Publishing (visibility=public) additionally has to pass `PublishChecklist`; failures come back as a `visibility` validation error. Change the shape in the game's rules class only.
