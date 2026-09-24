/* Downloads the theme's Google Fonts and writes a local @font-face sheet.
 *
 * Noto Sans Bengali is a variable font: ask Google for 400, 600 and 700 and it
 * answers with the same file three times, one weight named on each. Saved
 * under three names, a browser cannot tell they are the same and fetches 105KB
 * of Bengali three times over — 260KB of a 788KB page, for nothing. So every
 * download is hashed, identical bytes are written once, and the weights that
 * shared a file are declared as the range they really are. */
import fs from 'fs';
import crypto from 'crypto';

const ROOT = './';
const FONT_DIR = ROOT + 'assets/fonts/';
const CSS_URL = 'https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600;700&display=swap';

// Without a modern browser's user agent, Google answers with TTF instead of WOFF2.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const css = await (await fetch(CSS_URL, { headers: { 'User-Agent': UA } })).text();

fs.mkdirSync(FONT_DIR, { recursive: true });

// The subset name only appears as a comment before each face.
const commented = css.split(/\/\*\s*([a-z-]+)\s*\*\//i);
const pending = [];

for (let i = 1; i < commented.length; i += 2) {
  const subset = commented[i];
  const chunk = commented[i + 1] || '';
  chunk.split('@font-face').slice(1).forEach((block) => pending.push({ subset, block }));
}

const used = pending.length
  ? pending
  : css.split('@font-face').slice(1).map((block) => ({ subset: 'unknown', block }));

/* Downloaded bytes, keyed by hash, so the same file is written once. */
const byHash = new Map();
const faces = [];

for (const { subset, block } of used) {
  const family = /font-family:\s*'([^']+)'/.exec(block)?.[1];
  const weight = /font-weight:\s*(\d+)/.exec(block)?.[1];
  const url = /url\((https:[^)]+\.woff2)\)/.exec(block)?.[1];
  const range = /unicode-range:\s*([^;]+);/.exec(block)?.[1];
  const style = /font-style:\s*([a-z]+)/.exec(block)?.[1] || 'normal';

  if (!family || !weight || !url) continue;

  const bytes = Buffer.from(await (await fetch(url, { headers: { 'User-Agent': UA } })).arrayBuffer());
  const hash = crypto.createHash('sha256').update(bytes).digest('hex');
  const slug = family.toLowerCase().replace(/\s+/g, '-');

  let face = byHash.get(hash);

  if (face) {
    // The same bytes under another weight's name: one file, a wider range.
    face.weights.push(Number(weight));
    continue;
  }

  face = {
    family,
    style,
    subset,
    range,
    hash,
    weights: [Number(weight)],
    slug,
    bytes,
  };

  byHash.set(hash, face);
  faces.push(face);
}

/* Named only once the weights are known, so a variable file is not filed
   under whichever weight happened to be downloaded first. */
faces.forEach((f) => {
  const min = Math.min(...f.weights);
  const max = Math.max(...f.weights);

  f.file = `${f.slug}-${min === max ? min : 'variable'}-${f.subset}.woff2`;
  f.declared = min === max ? String(min) : `${min} ${max}`;

  fs.writeFileSync(FONT_DIR + f.file, f.bytes);
});

/* Anything left from an earlier run, now unreferenced, goes. */
const keep = new Set(faces.map((f) => f.file));
const stale = fs.readdirSync(FONT_DIR).filter((n) => n.endsWith('.woff2') && !keep.has(n));

stale.forEach((n) => fs.unlinkSync(FONT_DIR + n));

const out = `/**
 * The theme's typefaces, served from this site.
 *
 * Google's stylesheet cost a DNS lookup, a connection and two round trips
 * before a single Bengali glyph could be drawn — and handed every reader's
 * address to a third party on the way. These are the same files, from the
 * same source, under the SIL Open Font License.
 *
 * Where one file covers several weights it is a variable font, declared once
 * as the range it carries rather than downloaded once per weight.
 *
 * Generated; to change the weights, re-run the fonts script in the build
 * notes rather than editing this file.
 */

${faces
  .map(
    (f) => `/* ${f.family} ${f.declared} — ${f.subset} */
@font-face {
  font-family: '${f.family}';
  font-style: ${f.style};
  font-weight: ${f.declared};
  font-display: swap;
  src: url('../fonts/${f.file}') format('woff2');${f.range ? `\n  unicode-range: ${f.range};` : ''}
}`
  )
  .join('\n\n')}
`;

fs.writeFileSync(ROOT + 'assets/css/fonts.css', out);

const total = faces.reduce((a, f) => a + f.bytes.length, 0);
const saved = used.length - faces.length;

console.log(`Wrote ${faces.length} files, ${Math.round(total / 1024)}KB total.`);
console.log(`${saved} duplicate download${saved === 1 ? '' : 's'} collapsed into a shared file.`);
if (stale.length) console.log(`Removed ${stale.length} stale file(s): ${stale.join(', ')}`);
faces.forEach((f) => console.log(`  ${f.file.padEnd(42)} ${String(Math.round(f.bytes.length / 1024)).padStart(4)}KB  weight ${f.declared}`));
