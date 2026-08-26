#!/usr/bin/env node
/**
 * Selects the texture atlas sheets the app's Diablo IV icon manifest asks
 * for out of a CASC extraction directory, converts them to webp and names
 * them by Texture SNO id — the filename the app resolves at runtime
 * (public/games/diablo-4/icons/{sno}.webp).
 *
 * The extraction directory is whatever a CASC texture extractor produced
 * (PNG or WebP files named by texture object name, in any subdirectory
 * layout). Matching is by basename, case-insensitive.
 *
 * Usage:
 *   node pack.mjs --manifest <url-or-path> --input ./raw --out ./icons
 *
 * Requires `cwebp` on PATH (winget install Google.Libwebp). Pass
 * `--max-edge 2048` to downscale oversized sheets — safe, because the app's
 * icon crops are fractional UV rects, not pixel offsets. Without it sheets
 * keep their native size.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join, parse } from 'node:path';

const args = Object.fromEntries(
    process.argv
        .slice(2)
        .map((arg, index, list) =>
            arg.startsWith('--') ? [arg.slice(2), list[index + 1]] : null,
        )
        .filter(Boolean),
);

if (!args.manifest || !args.input) {
    console.error('Usage: node pack.mjs --manifest <url-or-path> --input <extraction dir> [--out ./icons] [--max-edge 2048]');
    process.exit(1);
}

const outDir = args.out ?? './icons';
const maxEdge = args['max-edge'] ? Number(args['max-edge']) : null;

const manifest = args.manifest.startsWith('http')
    ? await (await fetch(args.manifest)).json()
    : JSON.parse(readFileSync(args.manifest, 'utf8'));

const textures = manifest.textures ?? {};
const wanted = new Map(); // lowercased atlas name -> sno
for (const [sno, entry] of Object.entries(textures)) {
    if (entry?.name) {
        wanted.set(String(entry.name).toLowerCase(), sno);
    }
}

if (wanted.size === 0) {
    console.error('The manifest lists no texture names; re-run `php artisan d4:icon-manifest` on current data.');
    process.exit(1);
}

// Index the extraction directory recursively by basename.
const found = new Map(); // lowercased basename -> full path
const stack = [args.input];
while (stack.length > 0) {
    const dir = stack.pop();
    for (const name of readdirSync(dir)) {
        const full = join(dir, name);
        if (statSync(full).isDirectory()) {
            stack.push(full);
        } else if (/\.(png|webp)$/i.test(name)) {
            found.set(parse(name).name.toLowerCase(), full);
        }
    }
}

mkdirSync(outDir, { recursive: true });

let converted = 0;
const missing = [];

for (const [name, sno] of wanted) {
    const source = found.get(name);

    if (!source) {
        missing.push(`${sno} (${name})`);
        continue;
    }

    execFileSync('cwebp', [
        '-quiet',
        '-q', '80',
        // 0 preserves aspect. Only when asked: cwebp would upscale too.
        ...(maxEdge ? ['-resize', String(maxEdge), '0'] : []),
        source,
        '-o', join(outDir, `${sno}.webp`),
    ]);
    converted += 1;
}

console.log(`Converted ${converted}/${wanted.size} sheets into ${outDir}.`);

if (missing.length > 0) {
    console.log('\nNot found in the extraction directory (letter badges until extracted):');
    for (const line of missing) {
        console.log(`  - ${line}`);
    }
}

if (!existsSync(join(outDir, `${[...wanted.values()][0]}.webp`)) && converted === 0) {
    process.exit(2);
}
