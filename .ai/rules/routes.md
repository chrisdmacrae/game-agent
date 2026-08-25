---
paths:
  - 'routes/**'
---

# Routes

## Regenerate Wayfinder with --with-form after route changes
After editing routes or controller actions, run `php artisan wayfinder:generate --with-form --no-interaction`. The `--with-form` flag is required: auth/settings pages call `.form()` on generated routes, and a plain `wayfinder:generate` drops those helpers and breaks `npm run types:check`.
