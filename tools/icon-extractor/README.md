# PoE2 Icon Extractor (offline workflow)

Extracts the gem and unique-item icons referenced by the app's icon manifest
directly from Grinding Gear Games' patch CDN, converting them to PNG. Run this
on your own machine (Windows instructions below) whenever the game data is
re-imported after a patch — the app works fine without icons and upgrades
automatically once they're present.

## One-time setup (Windows)

1. Install [Node.js LTS](https://nodejs.org) (or `winget install OpenJS.NodeJS.LTS`)
2. Install ImageMagick: `winget install ImageMagick.ImageMagick` — then **reopen the terminal**
3. In this folder: `npm install`

## Run

```powershell
# Manifest straight from the deployed site (or use a local file path):
node extract.mjs --manifest https://yourdomain.com/games/poe2/icon-manifest.json
```

- The PoE2 client version is auto-detected from repoe-fork's version marker so
  the icons match the same client the app's data came from. Override with
  `--patch 4.5.4.10.2` if needed.
- Output lands in `./icons/*.png` (one file per manifest key). A `.work/`
  directory caches downloaded bundles — keep it around to make re-runs fast,
  delete it freely otherwise.

## Publish the icons

Copy the output so the files live at `public/games/poe2/icons/*.png` in the app:

- Local dev: copy into the repo at `public/games/poe2/icons/` and commit.
- Deployed app: same — the icons ship with the repo, so committing + deploying
  publishes them.

The app resolves icons by content-hash filename; missing files simply fall back
to the letter badges, so partial extractions are safe.

## Notes

- ~2,000 small files, a few MB total; first run downloads a few hundred MB of
  bundle data from the patch CDN into `.work/` (cached for re-runs).
- Game art is Grinding Gear Games' property, published here only as part of a
  fan tool per their community norms. Don't redistribute the icons separately.
