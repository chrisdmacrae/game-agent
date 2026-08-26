# D4 Icon Extractor (offline workflow)

Produces the texture atlas sheets the app's Diablo IV icon manifest asks for,
straight out of a local Diablo IV install's CASC storage. Run this on a
Windows machine with the game installed whenever the game data is re-imported
after a patch — the app works fine without the sheets and upgrades
automatically once they're present (missing sheets render as letter badges).

Unlike PoE2 there is no public patch CDN for Diablo IV art, so a game install
is the only supported pixel source.

## How the pieces fit

- `php artisan d4:icon-manifest` (runs automatically at the end of
  `d4:import`) writes `public/games/diablo-4/icon-manifest.json`: every
  texture atlas the imported entities reference, keyed by **Texture SNO id**,
  each with its atlas object `name` (e.g. `2DUI_Skills_Barbarian`).
- This tool extracts those textures from CASC and converts them to
  `{sno}.webp` sheets.
- The app crops individual icons out of a sheet with **fractional UV rects**
  stored at import time, so sheets are resolution-independent — downscaling
  them is fine.

## One-time setup (Windows)

1. Install [Node.js LTS](https://nodejs.org) (or `winget install OpenJS.NodeJS.LTS`)
2. Install the webp tools: `winget install Google.Libwebp` (for `cwebp`) —
   then **reopen the terminal**
3. Install [uv](https://docs.astral.sh/uv/) (`winget install astral-sh.uv`)
   and the extraction engine (pinned choice, verified CLI):

   ```powershell
   uv tool install git+https://github.com/game-strategy-hq/d4-asset-extractor
   ```

4. Download `texconv.exe` from
   [DirectXTex releases](https://github.com/microsoft/DirectXTex/releases)
   and place it in a `tools/` folder inside your working directory (the
   engine looks for `./tools/texconv.exe`).

## Run

```powershell
# 1. Extract the two UI atlas families as full PNG sheets (named by texture
#    object name, which is what pack.mjs matches on). Output: .\output\textures\
d4-extract extract "C:\Program Files (x86)\Diablo IV" --filter "2DUI*"
d4-extract extract "C:\Program Files (x86)\Diablo IV" --filter "2DInventory*"

# 2. Select, rename by texture SNO and convert the manifest's sheets:
node pack.mjs --manifest <path-or-url-to>/icon-manifest.json --input .\output\textures --out .\icons
```

The manifest lives at `public/games/diablo-4/icon-manifest.json` in the app
repo (regenerate with `php artisan d4:icon-manifest`); copy the file over or
point `--manifest` at the deployed site's URL.

`pack.mjs` reads the manifest, finds each texture by its `name` among the
extracted files (`<name>.png` / `<name>.webp`, case-insensitive), converts to
webp with `cwebp -q 80` (downscaling to at most 2048px on the long edge —
safe, because the app's crops are fractional), and writes `icons/{sno}.webp`.
It prints every manifest entry it could not find so a partial extraction is
visible, not silent.

## Publish the sheets

Copy the output so the files live at `public/games/diablo-4/icons/*.webp` in
the app, and commit — the sheets ship with the repo, the way the PoE2 icons
do. Partial sets are safe: entities whose sheet is missing keep their letter
badge.

## Notes

- Expect ~50–150 sheets, webp-compressed to a few MB total.
- Game art is Blizzard Entertainment's property, published here only as part
  of a fan tool. Don't redistribute the sheets separately.
