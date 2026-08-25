---
paths:
  - 'app/Http/Controllers/Auth/**'
---

# Auth

## Emailed single-use links: GET renders, POST consumes
Mail scanners and link previewers fetch emailed URLs before the human clicks, which would burn a single-use token. So `GET /login/verify/{token}` and `GET /settings/email/confirm/{token}` only render the auth/Verify page; that page auto-posts to the matching POST route, which is what actually consumes the token. Never move consumption into a GET handler.
