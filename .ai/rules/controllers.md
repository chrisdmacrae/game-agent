---
paths:
  - app/Http/Controllers/HomeController.php
---

# Controllers

## Homepage toolkits require a SERVERS map entry per live game
HomeController::SERVERS maps game slug → MCP server class (poe2 => Poe2Server, diablo-4 => D4Server). The homepage "What the servers expose" section renders one toolkit per live game from this map — flipping a game to is_live in the DB is not enough; without a SERVERS entry its toolkit is silently omitted. Tool lists are read per server via reflection on the protected $tools array, and model counts come from ModelDocRepository::all($slug) (content/games/{slug}/models).
