---
paths:
  - 'routes/**'
---

# Routes

## Regenerate Wayfinder with --with-form after route changes
After editing routes or controller actions, run `php artisan wayfinder:generate --with-form --no-interaction`. The `--with-form` flag is required: auth/settings pages call `.form()` on generated routes, and a plain `wayfinder:generate` drops those helpers and breaks `npm run types:check`.

## Static routes must be declared before the /{game:slug} wildcard
routes/web.php ends with a `Route::prefix('{game:slug}')` group that matches any single URL segment. Every fixed-first-segment route (login, my-builds, builds/*, settings/*) and the `require settings.php` must come BEFORE it, or the game hub swallows them and answers 404 through implicit binding. Fortify registers its own routes ahead of web.php, so /login is safe; anything you add here is not.
