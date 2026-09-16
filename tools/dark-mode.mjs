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
  '#fef3c7': '#2e2612', '#fee2e2': '#2f1b1b', '#e0e0e0': '#2a303a', '#ccc': '#39414d', '#cccccc': '#39414d', '#cccccc': '#39414d',
};
const BORDER = {
  '#e5e7eb': '#272d36', '#d1d5db': '#313947', '#f3f4f6': '#242a33', '#f0f0f0': '#242a33',
  '#eee': '#242a33', '#eeeeee': '#242a33', '#ddd': '#313947', '#dddddd': '#313947',
  '#9ca3af': '#3d4552', '#bae0f2': '#24425a', '#e0e0e0': '#2a303a', '#fca5a5': '#5a2b2b',
};
const TEXT = {
  '#1a1a1a': '#e7e9ec', '#111827': '#eceef2', '#1f2937': '#dde1e7', '#1f2421': '#dde1e7',
  '#1e293b': '#dde1e7', '#374151': '#c5ccd6', '#4b5563': '#b7bfca', '#6b7280': '#9aa3b0',
  '#64748b': '#9aa3b0', '#9ca3af': '#8d96a4', '#50575e': '#9aa3b0', '#666666': '#9aa3b0',
  '#0080ff': '#6bb6ff', '#0b64c9': '#6bb6ff', '#5f6772': '#9aa3b0', '#a9b1bd': '#a9b1bd',
  '#333333': '#c5ccd6', '#111111': '#eceef2', '#000000': '#e7e9ec', '#222222': '#dde1e7', '#444444': '#c5ccd6',
};

/* Only controls that carry their own brand colour on both sides. */
const SKIP = /bb-share__btn|bb-badge|bb-toast/i;
const SKIP_EXACT = new Set(['body.bb-body']);

const expand = hex => {
  const h = hex.toLowerCase();
  return h.length === 4 ? '#' + h[1] + h[1] + h[2] + h[2] + h[3] + h[3] : h;
};

const toHex = (r, g, b) => '#' + [r, g, b].map(n => Number(n).toString(16).padStart(2, '0')).join('');

/* Colours are written three ways in this stylesheet — #fff, #ffffff and
   rgba(255, 255, 255, .82) — and the dark palette has to recognise all three.
   A light panel written as rgba() was how the table-of-contents button ended
   up with pale text on a pale ground. */
function mapValue(value, table) {
  return value
    .replace(/#[0-9a-fA-F]{3,8}\b/g, hex => table[expand(hex)] || hex)
    .replace(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)\s*(?:[,/]\s*([\d.]+)\s*)?\)/g, (whole, r, g, b, alpha) => {
      const mapped = table[toHex(r, g, b)];
      if (!mapped) return whole;

      const hex = expand(mapped);
      const parts = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));

      return alpha === undefined
        ? 'rgb(' + parts.join(', ') + ')'
        : 'rgba(' + parts.join(', ') + ', ' + alpha + ')';
    });
}

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
    const hasColour = /#[0-9a-fA-F]{3,8}\b/.test(value) || /rgba?\(/i.test(value) || /var\(--bb-brand-blue[,)]/.test(value);
    if (!hasColour) return;

    let mapped = null;
    if (prop === 'background' || prop === 'background-color') mapped = mapValue(value, SURFACE);
    else if (prop === 'color') mapped = mapValue(value, TEXT);
    else if (/^border(-(top|right|bottom|left|color))?$/.test(prop)) mapped = mapValue(value, BORDER);

    /* The brand blue reads at 3.9:1 as text on the dark ground — fine as a
       button, not as a word. Lifted only where it is the text colour. */
    if (prop === 'color' && /var\(--bb-brand-blue[,)]/.test(value)) mapped = '#6bb6ff';

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
