/* Regenerates the dark-mode section of style.css from the rules above it.
   Run it whenever light-mode CSS changes. */
import fs from 'fs';

const FILE = 'style.css';
const raw = fs.readFileSync(FILE, 'utf8');
const isCrlf = raw.includes('\r\n');
const css = raw.replace(/\r\n/g, '\n');

const SURFACE = {
  '#fff': '#171b21', '#ffffff': '#171b21',
  '#fafafa': '#1b2028', '#f9fafb': '#1b2028', '#f8fafc': '#1b2028', '#f8f8f8': '#1b2028',
  '#f3f4f6': '#1d222a', '#f0f0f0': '#1d222a', '#f1f5f9': '#1d222a', '#eee': '#1d222a', '#eeeeee': '#1d222a',
  '#f0f9ff': '#132234', '#e0f2fe': '#132234', '#e6f2ff': '#132234', '#eff6ff': '#132234',
  '#fef3c7': '#2e2612', '#fee2e2': '#2f1b1b', '#e0e0e0': '#2a303a', '#ccc': '#39414d', '#cccccc': '#39414d',
};
const BORDER = {
  '#e5e7eb': '#272d36', '#d1d5db': '#313947', '#f3f4f6': '#242a33', '#f0f0f0': '#242a33',
  '#eee': '#242a33', '#eeeeee': '#242a33', '#ddd': '#313947', '#dddddd': '#313947',
  '#9ca3af': '#3d4552', '#bae0f2': '#24425a', '#e0e0e0': '#2a303a', '#fca5a5': '#5a2b2b',
};
const TEXT = {
  '#1a1a1a': '#e7e9ec', '#111827': '#eceef2', '#1f2937': '#dde1e7', '#1f2421': '#dde1e7',
  '#1e293b': '#dde1e7', '#374151': '#c5ccd6', '#4b5563': '#b7bfca', '#6b7280': '#9aa3b0',
  '#64748b': '#9aa3b0', '#9ca3af': '#8d96a4', '#50575e': '#9aa3b0', '#666': '#9aa3b0',
};

const SKIP = /bb-share__btn|bb-badge|bb-toast|bb-video-modal|bb-hero__play|bb-masthead__yt|bb-footer__yt|katex/i;
const SKIP_EXACT = new Set(['body.bb-body']);

const mapValue = (value, table) => value.replace(/#[0-9a-fA-F]{3,8}\b/g, hex => table[hex.toLowerCase()] || hex);

function parseRules(text) {
  const rules = [];
  const clean = text.replace(/\/\*[\s\S]*?\*\//g, '');

  (function walk(body, media) {
    let i = 0;
    while (i < body.length) {
      const brace = body.indexOf('{', i);
      if (brace === -1) break;
      const prelude = body.slice(i, brace).trim();
      let depth = 1, j = brace + 1;
      while (j < body.length && depth > 0) {
        if (body[j] === '{') depth++;
        else if (body[j] === '}') depth--;
        j++;
      }
      const inner = body.slice(brace + 1, j - 1);
      if (/^@media/i.test(prelude)) walk(inner, prelude);
      else if (!prelude.startsWith('@') && prelude) rules.push({ media, selector: prelude, body: inner });
      i = j;
    }
  })(clean, '');

  return rules;
}

function darkDeclarations(body) {
  const out = [];
  body.split(';').forEach(decl => {
    const m = /^\s*([a-zA-Z-]+)\s*:\s*([^:]+)$/.exec(decl);
    if (!m) return;
    const prop = m[1].toLowerCase();
    const value = m[2].trim();
    if (!/#[0-9a-fA-F]{3,8}\b/.test(value)) return;

    let mapped = null;
    if (prop === 'background' || prop === 'background-color') mapped = mapValue(value, SURFACE);
    else if (prop === 'color') mapped = mapValue(value, TEXT);
    else if (/^border(-(top|right|bottom|left|color))?$/.test(prop)) mapped = mapValue(value, BORDER);

    if (mapped && mapped !== value) out.push(`  ${prop}: ${mapped};`);
  });
  return out;
}

const darkSelector = selector => selector.split(',').map(part => {
  const s = part.trim();
  return (s === 'html' || s === ':root') ? '[data-theme="dark"]' : '[data-theme="dark"] ' + s;
}).join(',\n');

const light = css.replace(/\/\* === BEGIN GENERATED DARK MODE === \*\/[\s\S]*?\/\* === END GENERATED DARK MODE === \*\//, '').trimEnd();

const byMedia = new Map();
let count = 0;

parseRules(light).forEach(rule => {
  if (SKIP.test(rule.selector) || SKIP_EXACT.has(rule.selector.trim())) return;
  const decls = darkDeclarations(rule.body);
  if (!decls.length) return;
  const block = `${darkSelector(rule.selector)} {\n${decls.join('\n')}\n}`;
  if (!byMedia.has(rule.media)) byMedia.set(rule.media, []);
  byMedia.get(rule.media).push(block);
  count++;
});

let dark = `

/* === BEGIN GENERATED DARK MODE === */
/* =========================================================
   26. DARK MODE

   Built from the rules above: every light surface, every light
   border and every dark text colour in this stylesheet has a
   dark counterpart here. Regenerate rather than hand-edit.
   ========================================================= */

[data-theme="dark"] {
  color-scheme: dark;
  /* Paints the area behind an over-scroll, where the body does not reach. */
  background: #171b21;
  --bb-brand-blue-light: #16263a;
}

/* Written here rather than generated: the body's own white background sits
   over the root's, so this is the rule the whole page rests on. */
[data-theme="dark"] body.bb-body {
  background: #171b21;
  color: #e7e9ec;
}

/* #0080ff on a near-black ground is legible but tiring over a long article,
   so body links lift a little. */
[data-theme="dark"] .bb-content a,
[data-theme="dark"] .bb-references__item a,
[data-theme="dark"] .bb-authorpage__site a { color: #6bb6ff; }

[data-theme="dark"] .bb-topbar__theme:hover { background: rgba(255, 255, 255, 0.14); }

[data-theme="dark"] ::-webkit-scrollbar-thumb { background: #39414d; }

`;

for (const [media, blocks] of byMedia) {
  if (!media) dark += blocks.join('\n\n') + '\n\n';
  else dark += `${media} {\n` + blocks.map(b => b.split('\n').map(l => '  ' + l).join('\n')).join('\n\n') + '\n}\n\n';
}

dark += '/* === END GENERATED DARK MODE === */\n';

const out = light + dark;
fs.writeFileSync(FILE, isCrlf ? out.replace(/\n/g, '\r\n') : out);
console.log(`Dark mode regenerated: ${count} rules, ${byMedia.size} context(s).`);
