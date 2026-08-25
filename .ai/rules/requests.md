---
paths:
  - app/Http/Requests/BuildUpdateRequest.php
---

# Requests

## The web editor validates with BuildRules, same as the MCP tool
BuildUpdateRequest merges `BuildRules::rules('build.')` rather than restating the payload shape, and the controller runs the payload through `BuildPayload::normalize()` and `BuildValidator` exactly like SaveBuildTool does. Publishing (visibility=public) additionally has to pass `PublishChecklist`; failures come back as a `visibility` validation error. Change the shape in BuildRules only.
