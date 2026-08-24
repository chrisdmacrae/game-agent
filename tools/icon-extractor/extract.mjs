#!/usr/bin/env node
/**
 * PoE2 icon extractor — offline workflow.
 *
 * Reads the app's icon-manifest.json (a map of {key: ddsPath}), downloads the
 * referenced art files from Grinding Gear Games' patch CDN via pathofexile-dat,
 * converts them to PNG (requires ImageMagick), and writes out/{key}.png files
 * ready to copy into the app's public/games/poe2/icons/ directory.
 *
 * Usage:
 *   node extract.mjs --manifest <path-or-url> [--patch <client-version>] [--out <dir>]
 *
 * Examples:
 *   node extract.mjs --manifest https://yourdomain.com/games/poe2/icon-manifest.json
 *   node extract.mjs --manifest ./icon-manifest.json --patch 4.5.4.10.2 --out ./icons
 */
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, readdirSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const VERSION_URL = 'https://repoe-fork.github.io/poe2/version.txt';

function arg(name, fallback = null) {
    const index = process.argv.indexOf(`--${name}`);
    return index !== -1 && process.argv[index + 1] ? process.argv[index + 1] : fallback;
}

async function loadManifest(source) {
    if (/^https?:\/\//.test(source)) {
        const response = await fetch(source);
        if (!response.ok) throw new Error(`Failed to download manifest (HTTP ${response.status})`);
        return response.json();
    }
    return JSON.parse(readFileSync(source, 'utf8'));
}

async function resolvePatch(explicit) {
    if (explicit) return explicit;
    const response = await fetch(VERSION_URL);
    if (!response.ok) throw new Error(`Could not auto-detect the PoE2 client version; pass --patch <version>`);
    const version = (await response.text()).trim();
    console.log(`Auto-detected PoE2 client version ${version} (from repoe-fork)`);
    return version;
}

function checkImageMagick() {
    return new Promise((resolve) => {
        const proc = spawn('magick', ['-version'], { stdio: 'ignore', shell: process.platform === 'win32' });
        proc.on('error', () => resolve(false));
        proc.on('exit', (code) => resolve(code === 0));
    });
}

function runExtractor(workDir) {
    return new Promise((resolve, reject) => {
        const proc = spawn('npx', ['pathofexile-dat'], {
            cwd: workDir,
            stdio: 'inherit',
            shell: process.platform === 'win32',
        });
        proc.on('error', reject);
        proc.on('exit', (code) => (code === 0 ? resolve() : reject(new Error(`pathofexile-dat exited with code ${code}`))));
    });
}

const manifestSource = arg('manifest');

if (!manifestSource) {
    console.error('Usage: node extract.mjs --manifest <path-or-url> [--patch <client-version>] [--out <dir>]');
    process.exit(1);
}

if (!(await checkImageMagick())) {
    console.error('ImageMagick ("magick" command) is required to convert .dds files.');
    console.error('Windows: winget install ImageMagick.ImageMagick   (then reopen the terminal)');
    process.exit(1);
}

const manifest = await loadManifest(manifestSource);
const icons = manifest.icons ?? {};
// Defensive: junk art paths in the game data would abort pathofexile-dat.
const entries = Object.entries(icons).filter(([, dds]) => /^Art\/.+\.dds$/i.test(dds));
const skipped = Object.keys(icons).length - entries.length;
if (skipped > 0) console.warn(`Skipping ${skipped} invalid art path(s) from the manifest.`);

if (entries.length === 0) {
    console.error('Manifest contains no icons.');
    process.exit(1);
}

const patch = await resolvePatch(arg('patch'));
const outDir = path.resolve(arg('out', './icons'));
const workDir = path.resolve('./.work');

console.log(`Extracting ${entries.length} icons for client ${patch}...`);

mkdirSync(workDir, { recursive: true });
mkdirSync(outDir, { recursive: true });

writeFileSync(
    path.join(workDir, 'config.json'),
    JSON.stringify({ patch, files: entries.map(([, dds]) => dds), tables: [] }, null, 2),
);

await runExtractor(workDir);

// pathofexile-dat writes files/Art@2DArt@...@Name.png; rename to {key}.png.
const filesDir = path.join(workDir, 'files');
const produced = new Map(
    readdirSync(filesDir).map((file) => [file.replaceAll('@', '/').replace(/\.png$/, '.dds'), file]),
);

let copied = 0;
const missing = [];

for (const [key, dds] of entries) {
    const file = produced.get(dds);
    if (file) {
        renameSync(path.join(filesDir, file), path.join(outDir, `${key}.png`));
        copied++;
    } else {
        missing.push(dds);
    }
}

rmSync(filesDir, { recursive: true, force: true });

console.log(`\nDone: ${copied}/${entries.length} icons written to ${outDir}`);

if (missing.length > 0) {
    console.warn(`Missing (not found in game files): ${missing.length}`);
    for (const dds of missing.slice(0, 10)) console.warn(`  - ${dds}`);
}

console.log('\nNext: copy the folder into the app so the files live at public/games/poe2/icons/*.png');
