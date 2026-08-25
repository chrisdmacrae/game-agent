---
paths:
  - 'app/Http/Controllers/Auth/**'
---

# Auth

## Emailed single-use links: GET renders, POST consumes
Mail scanners and link previewers fetch emailed URLs before the human clicks, which would burn a single-use token. So `GET /login/verify/{token}` and `GET /settings/email/confirm/{token}` only render the auth/Verify page; that page auto-posts to the matching POST route, which is what actually consumes the token. Never move consumption into a GET handler.

## consume() must answer with Inertia::location, not redirect()->intended()
auth/Verify posts to `login.verify.store` over XHR. The post-login destination is not always an Inertia page: MCP clients arrive via Passport's `/oauth/authorize`, which renders the Blade view `mcp.authorize`, so an XHR that follows a plain redirect gets raw HTML and dies before the consent screen.

So consume() pulls `url.intended` itself (`$request->session()->pull('url.intended', route('my-builds'))`) and returns `Inertia::location($url)`. That 409s Inertia requests into a real browser visit and still answers plain requests with a 302, so non-XHR tests keep working.

The success toast must go through `Inertia::flash()` (session-backed) — it survives the extra request the location visit costs and fires on the next page load. A prop would not.
