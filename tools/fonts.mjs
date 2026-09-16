/* Downloads the theme's Google Fonts and writes a local @font-face sheet. */
import fs from 'fs';
import path from 'path';

const ROOT = './';
const FONT_DIR = ROOT + 'assets/fonts/';
const CSS_URL = 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Noto+Sans+Bengali:wght@400;600;700&display=swap';

// Without a modern browser's user agent, Google answers with TTF instead of WOFF2.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const css = await (await fetch(CSS_URL, { headers: { 'User-Agent': UA } })).text();

fs.mkdirSync(FONT_DIR, { recursive: true });

const blocks = css.split('@font-face').slice(1);
const faces = [];
let currentSubset = 'unknown';

// The subset name only appears as a comment before each face.
const commented = css.split(/\/\*\s*([a-z-]+)\s*\*\//i);

let pending = [];
for (let i = 1; i < commented.length; i += 2) {
  const subset = commented[i];
  const chunk = commented[i + 1] || '';
  chunk.split('@font-face').slice(1).forEach(block => pending.push({ subset, block }));
}

const used = pending.length ? pending : blocks.map(block => ({ subset: currentSubset, block }));

for (const { subset, block } of used) {
  const family = /font-family:\s*'([^']+)'/.exec(block)?.[1];
  const weight = /font-weight:\s*(\d+)/.exec(block)?.[1];
  const url = /url\((https:[^)]+\.woff2)\)/.exec(block)?.[1];
  const range = /unicode-range:\s*([^;]+);/.exec(block)?.[1];
  const style = /font-style:\s*([a-z]+)/.exec(block)?.[1] || 'normal';

  if (!family || !weight || !url) continue;

  const slug = family.toLowerCase().replace(/\s+/g, '-');
  const file = `${slug}-${weight}-${subset}.woff2`;

  const bytes = Buffer.from(await (await fetch(url, { headers: { 'User-Agent': UA } })).arrayBuffer());
  fs.writeFileSync(FONT_DIR + file, bytes);

  faces.push({ family, weight, style, subset, file, range, size: bytes.length });
}

const byFamily = {};
faces.forEach(f => { byFamily[f.family] = (byFamily[f.family] || 0) + 1; });

const out = `/**
 * The theme's typefaces, served from this site.
 *
 * Google's stylesheet cost a DNS lookup, a connection and two round trips
 * before a single Bengali glyph could be drawn — and handed every reader's
 * address to a third party on the way. These are the same files, from the
 * same source, under the SIL Open Font License.
 *
 * Generated; to change the weights, re-run the fonts script in the build
 * notes rather than editing this file.
 */

${faces.map(f => `/* ${f.family} ${f.weight} — ${f.subset} */
@font-face {
  font-family: '${f.family}';
  font-style: ${f.style};
  font-weight: ${f.weight};
  font-display: swap;
  src: url('../fonts/${f.file}') format('woff2');${f.range ? `\n  unicode-range: ${f.range};` : ''}
}`).join('\n\n')}
`;

fs.writeFileSync(ROOT + 'assets/css/fonts.css', out);

const total = faces.reduce((a, f) => a + f.size, 0);
console.log(`Saved ${faces.length} files, ${Math.round(total / 1024)}KB total`);
Object.entries(byFamily).forEach(([k, v]) => console.log(`  ${k}: ${v} files`));
console.log('Subsets: ' + [...new Set(faces.map(f => f.subset))].join(', '));
console.log('Largest: ' + faces.slice().sort((a, b) => b.size - a.size).slice(0, 3).map(f => `${f.file} ${Math.round(f.size / 1024)}KB`).join(', '));
