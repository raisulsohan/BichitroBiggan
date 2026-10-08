/**
 * The interactive 3D solar system at /solarsystem (page-solarsystem.php).
 *
 * Opens in explore mode: drag or touch to turn the scene, scroll or pinch to
 * zoom, click a planet to fly to it and read about it. A guided tour plays the
 * 75-second camera journey with a full player; pausing it hands the scene
 * back to the viewer. Speaks Bengali, or English on the /en edition
 * (data-lang on the stage, set by page-solarsystem.php).
 *
 * three.js and the addons come from assets/vendor/three through the import
 * map inc/solarsystem.php prints. Every texture is painted in code, so the
 * page downloads no images. Built to solarsystem.min.js by npm run build.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

const root = document.getElementById('bb-solar');
if (root) main();

function main() {
const $ = (name) => root.querySelector('[data-s="' + name + '"]');

function hasWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
  } catch (e) {
    return false;
  }
}

function fail() {
  root.classList.add('is-nogl');
  $('nogl').hidden = false;
  $('loading').hidden = true;
}

if (!hasWebGL()) {
  fail();
  return;
}

const DUR = 75, FPS = 30, TOTAL = DUR * FPS;
const EN = root.dataset.lang === 'en';
// numbers a reader sees are Bengali on the Bengali edition, as everywhere on the site
const bn = EN ? String : (s) => String(s).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
const clamp = (x, a, b) => Math.min(b, Math.max(a, x));
const ease = (x) => { x = clamp(x, 0, 1); return x * x * (3 - 2 * x); };
const smooth = (e0, e1, x) => ease((x - e0) / (e1 - e0));
const easeInOut = (x) => { x = clamp(x, 0, 1); return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2; };

const COARSE = matchMedia('(pointer: coarse)').matches;
const IS_MOBILE = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || COARSE;
const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
/* Phones get smaller textures, fewer triangles and a lower resolution. */
const Q = IS_MOBILE
  ? { tex: 384, moon: 192, ring: 512, seg: [48, 32], sunSeg: [64, 40], atmoSeg: [40, 24], stars: 3500, belt: 900, dpr: 1.25, minDpr: 0.75, bloom: 128 }
  : { tex: 768, moon: 384, ring: 1024, seg: [96, 64], sunSeg: [96, 64], atmoSeg: [64, 32], stars: 6000, belt: 1800, dpr: 2, minDpr: 1, bloom: 256 };

/* ---------------- deterministic noise ---------------- */
function mulberry32(a) { return () => { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
const rng = mulberry32(20261008);
const perm = new Uint8Array(512);
{ const p = [...Array(256).keys()]; for (let i = 255; i > 0; i--) { const j = Math.floor(rng() * (i + 1)); [p[i], p[j]] = [p[j], p[i]]; } for (let i = 0; i < 512; i++) perm[i] = p[i & 255]; }
const fade = (t) => t * t * t * (t * (t * 6 - 15) + 10);
const lerp = (a, b, t) => a + (b - a) * t;
function grad(h, x, y, z) { h &= 15; const u = h < 8 ? x : y, v = h < 4 ? y : (h === 12 || h === 14 ? x : z); return ((h & 1) ? -u : u) + ((h & 2) ? -v : v); }
function noise(x, y, z) {
  const X = Math.floor(x) & 255, Y = Math.floor(y) & 255, Z = Math.floor(z) & 255;
  x -= Math.floor(x); y -= Math.floor(y); z -= Math.floor(z);
  const u = fade(x), v = fade(y), w = fade(z);
  const A = perm[X] + Y, AA = perm[A] + Z, AB = perm[A + 1] + Z, B = perm[X + 1] + Y, BA = perm[B] + Z, BB = perm[B + 1] + Z;
  return lerp(lerp(lerp(grad(perm[AA], x, y, z), grad(perm[BA], x - 1, y, z), u), lerp(grad(perm[AB], x, y - 1, z), grad(perm[BB], x - 1, y - 1, z), u), v),
    lerp(lerp(grad(perm[AA + 1], x, y, z - 1), grad(perm[BA + 1], x - 1, y, z - 1), u), lerp(grad(perm[AB + 1], x, y - 1, z - 1), grad(perm[BB + 1], x - 1, y - 1, z - 1), u), v), w);
}
function fbm(x, y, z, oct = 5) { let s = 0, a = 0.5, f = 1; for (let i = 0; i < oct; i++) { s += a * noise(x * f, y * f, z * f); f *= 2; a *= 0.5; } return s; }
const mix3 = (a, b, t) => [lerp(a[0], b[0], t), lerp(a[1], b[1], t), lerp(a[2], b[2], t)];

/* paint equirectangular textures from a function of the point on the unit sphere.
   fn returns a colour array, or { map, bumpMap, roughnessMap, emissiveMap } with arrays or grey 0..255 numbers */
const COLOR_MAPS = new Set(['map', 'emissiveMap']);
function paintMaps(w, h, fn) {
  const bufs = {};
  for (let j = 0; j < h; j++) {
    const lat = (0.5 - (j + 0.5) / h) * Math.PI, cl = Math.cos(lat), sl = Math.sin(lat);
    for (let i = 0; i < w; i++) {
      const lon = ((i + 0.5) / w) * Math.PI * 2 - Math.PI;
      const res = fn(cl * Math.cos(lon), sl, cl * Math.sin(lon), lat, lon);
      const o = Array.isArray(res) ? { map: res } : res, k = (j * w + i) * 4;
      for (const key in o) {
        const d = bufs[key] || (bufs[key] = new Uint8ClampedArray(w * h * 4));
        const c = typeof o[key] === 'number' ? [o[key], o[key], o[key]] : o[key];
        d[k] = c[0]; d[k + 1] = c[1]; d[k + 2] = c[2]; d[k + 3] = c.length > 3 ? c[3] : 255;
      }
    }
  }
  const out = {};
  for (const key in bufs) {
    const cv = document.createElement('canvas'); cv.width = w; cv.height = h;
    cv.getContext('2d').putImageData(new ImageData(bufs[key], w, h), 0, 0);
    const tex = new THREE.CanvasTexture(cv); tex.anisotropy = 4;
    if (COLOR_MAPS.has(key)) tex.colorSpace = THREE.SRGBColorSpace;
    out[key] = tex;
  }
  return out;
}
const paint = (w, h, fn) => paintMaps(w, h, fn).map;

const PAINTERS = {
  mercury: (x, y, z) => {
    const n = fbm(x * 3, y * 3, z * 3), c = Math.abs(noise(x * 9, y * 9, z * 9)), c2 = Math.abs(noise(x * 22, y * 22, z * 22));
    const v = 125 + n * 90 - c * 60;
    return { map: [v * 1.02, v, v * 0.95], bumpMap: 128 + n * 140 - (1 - c) * 50 - (1 - c2) * 30 };
  },
  venus: (x, y, z) => { const sw = fbm(x * 2, y * 2, z * 2); const n = fbm(x * 1.5 + sw, y * 7, z * 1.5 + sw); return mix3([205, 160, 95], [245, 220, 165], 0.5 + n); },
  earth: (x, y, z, lat) => {
    const n = fbm(x * 1.8 + 3, y * 1.8, z * 1.8, 6), m = fbm(x * 5, y * 5 + 7, z * 5);
    const al = Math.abs(lat), BLACK = [0, 0, 0];
    if (al > 1.22 || (al > 1.05 && n > -0.02)) return { map: [235, 240, 246], bumpMap: 60, roughnessMap: 200, emissiveMap: BLACK };
    if (n < 0.04) return { map: mix3([8, 30, 78], [24, 92, 158], clamp(1 + n * 4, 0, 1)), bumpMap: 0, roughnessMap: 80, emissiveMap: BLACK };
    const dry = clamp(1 - Math.abs(al - 0.42) * 3.2, 0, 1) * (0.5 + m);
    let c = mix3([52, 104, 44], [178, 150, 96], clamp(dry, 0, 1));
    if (n > 0.28) c = mix3(c, [120, 104, 90], clamp((n - 0.28) * 5, 0, 1));
    // night-side city lights: clustered, denser near coasts, absent in deserts
    const city = noise(x * 45, y * 45, z * 45) + 0.5 * fbm(x * 9 + 2, y * 9, z * 9) + (n < 0.12 ? 0.18 : 0) - dry * 0.35;
    const li = smooth(0.22, 0.45, city);
    return { map: c, bumpMap: 40 + (n - 0.04) * 520 + m * 40, roughnessMap: 235, emissiveMap: [255 * li, 196 * li, 120 * li] };
  },
  clouds: (x, y, z) => { const n = fbm(x * 2.6 + 11, y * 4, z * 2.6, 6); const a = smooth(0.0, 0.32, n) * 235; return [255, 255, 255, a]; },
  moon: (x, y, z) => {
    const n = fbm(x * 2.5, y * 2.5, z * 2.5), c = Math.abs(noise(x * 11, y * 11, z * 11)), c2 = Math.abs(noise(x * 26, y * 26, z * 26));
    const v = 150 + n * 110 - c * 50;
    return { map: [v, v, v * 0.98], bumpMap: 128 + n * 120 - (1 - c) * 50 - (1 - c2) * 30 };
  },
  mars: (x, y, z, lat) => {
    const n = fbm(x * 2.2, y * 2.2, z * 2.2, 6), m = fbm(x * 6, y * 6, z * 6);
    const bump = 128 + n * 200 + m * 60;
    if (Math.abs(lat) > 1.33) return { map: [240, 232, 225], bumpMap: bump };
    return { map: mix3([196, 98, 52], [110, 52, 34], clamp(0.5 + n * 1.6 + m * 0.4, 0, 1)), bumpMap: bump };
  },
  jupiter: (x, y, z, lat, lon) => {
    const tb = fbm(x * 3, y * 3, z * 3, 6);
    const b = Math.sin(lat * 15 + tb * 2.6);
    const light = mix3([232, 215, 182], [208, 170, 120], tb + 0.4), dark = mix3([165, 110, 70], [205, 145, 95], tb + 0.5);
    let c = mix3(dark, light, smooth(-0.6, 0.6, b + Math.sin(lat * 41 + tb * 5) * 0.25));
    let dl = lon - 0.9; dl = Math.atan2(Math.sin(dl), Math.cos(dl));
    const e = (dl / 0.3) ** 2 + ((lat + 0.36) / 0.12) ** 2;
    if (e < 1.4) c = mix3(c, mix3([196, 86, 52], [225, 140, 95], 0.5 + tb), smooth(1.4, 0.6, e));
    return c;
  },
  saturn: (x, y, z, lat) => { const tb = fbm(x * 3, y * 3, z * 3); const b = Math.sin(lat * 22 + tb * 1.6); return mix3([228, 204, 152], [192, 158, 104], 0.5 + b * 0.35 + tb * 0.4); },
  uranus: (x, y, z, lat) => { const tb = fbm(x * 2, y * 2, z * 2); return mix3([150, 214, 222], [178, 232, 236], 0.5 + Math.sin(lat * 10) * 0.18 + tb * 0.3); },
  neptune: (x, y, z, lat, lon) => {
    const tb = fbm(x * 3, y * 3, z * 3); let c = mix3([38, 70, 180], [72, 112, 222], 0.5 + Math.sin(lat * 12 + tb * 2) * 0.35 + tb * 0.3);
    let dl = lon + 1.2; dl = Math.atan2(Math.sin(dl), Math.cos(dl)); const e = (dl / 0.22) ** 2 + ((lat + 0.4) / 0.1) ** 2;
    if (e < 1) c = mix3(c, [20, 34, 100], smooth(1, 0.3, e));
    return c;
  },
};

/* ---------------- data ----------------
   info: the three lines the tour's caption shows. facts: the card in explore mode. */
const PLANETS = [
  { key: 'mercury', bn: 'বুধ', en: 'MERCURY', color: '#b5aea4', r: 0.55, a: 13, T: 0.24, a0: 0.5, tilt: 0.03, spin: 0.3,
    info: ['সূর্য থেকে দূরত্ব: ৫.৮ কোটি কিমি', 'এক বছর: মাত্র ৮৮ দিন', 'সৌরজগতের সবচেয়ে ছোট গ্রহ'],
    facts: [['ব্যাস', '৪,৮৭৯ কিমি'], ['নিজ অক্ষে এক পাক', 'প্রায় ৫৯ দিন'], ['এক বছর', '৮৮ দিন'], ['উপগ্রহ', 'নেই'], ['সূর্য থেকে দূরত্ব', '৫.৮ কোটি কিমি']],
    note: 'সৌরজগতের সবচেয়ে ছোট আর সূর্যের সবচেয়ে কাছের গ্রহ। বাতাস নেই বললেই চলে, তাই দিনে প্রচণ্ড গরম আর রাতে হাড়কাঁপানো ঠান্ডা।' },
  { key: 'venus', bn: 'শুক্র', en: 'VENUS', color: '#e9c88a', r: 0.95, a: 18, T: 0.62, a0: 2.1, tilt: 3.09, spin: 0.1, atmo: [1, 0.82, 0.5],
    info: ['সূর্য থেকে দূরত্ব: ১০.৮ কোটি কিমি', 'এক বছর: ২২৫ দিন', 'সবচেয়ে উত্তপ্ত গ্রহ — প্রায় ৪৬৫°সে'],
    facts: [['ব্যাস', '১২,১০৪ কিমি'], ['নিজ অক্ষে এক পাক', '২৪৩ দিন (উল্টো দিকে)'], ['এক বছর', '২২৫ দিন'], ['উপগ্রহ', 'নেই'], ['সূর্য থেকে দূরত্ব', '১০.৮ কোটি কিমি']],
    note: 'ঘন কার্বন ডাই-অক্সাইডের মেঘে ঢাকা বলে সবচেয়ে উত্তপ্ত গ্রহ — প্রায় ৪৬৫°সে। এখানে এক দিন এক বছরের চেয়েও লম্বা।' },
  { key: 'earth', bn: 'পৃথিবী', en: 'EARTH', color: '#4b8fd6', r: 1.0, a: 24, T: 1, a0: 4.0, tilt: 0.41, spin: 0.6, atmo: [0.35, 0.62, 1],
    info: ['সূর্য থেকে দূরত্ব: ১৫ কোটি কিমি', 'এক বছর: ৩৬৫ দিন', 'প্রাণের একমাত্র জানা আবাস · উপগ্রহ: চাঁদ'],
    facts: [['ব্যাস', '১২,৭৪২ কিমি'], ['নিজ অক্ষে এক পাক', '২৩ ঘণ্টা ৫৬ মিনিট'], ['এক বছর', '৩৬৫.২৫ দিন'], ['উপগ্রহ', '১টি — চাঁদ'], ['সূর্য থেকে দূরত্ব', '১৫ কোটি কিমি']],
    note: 'প্রাণের একমাত্র জানা আবাস। রাতের দিকে তাকালে শহরের আলো দেখা যায়; পৃষ্ঠের প্রায় ৭১ ভাগ পানিতে ঢাকা।' },
  { key: 'mars', bn: 'মঙ্গল', en: 'MARS', color: '#c9643a', r: 0.72, a: 31, T: 1.88, a0: 1.0, tilt: 0.44, spin: 0.6,
    info: ['সূর্য থেকে দূরত্ব: ২২.৮ কোটি কিমি', 'এক বছর: ৬৮৭ দিন', 'লাল গ্রহ — লোহার মরিচা-রঙা ধুলোয় ঢাকা'],
    facts: [['ব্যাস', '৬,৭৭৯ কিমি'], ['নিজ অক্ষে এক পাক', '২৪ ঘণ্টা ৩৭ মিনিট'], ['এক বছর', '৬৮৭ দিন'], ['উপগ্রহ', '২টি — ফোবস ও ডিমোস'], ['সূর্য থেকে দূরত্ব', '২২.৮ কোটি কিমি']],
    note: 'লাল গ্রহ — লোহার মরিচা-রঙা ধুলোয় ঢাকা। সৌরজগতের সবচেয়ে উঁচু আগ্নেয়গিরি অলিম্পাস মন্স এখানেই।' },
  { key: 'jupiter', bn: 'বৃহস্পতি', en: 'JUPITER', color: '#d7b48a', r: 3.2, a: 48, T: 11.86, a0: 3.0, tilt: 0.05, spin: 0.9,
    info: ['সূর্য থেকে দূরত্ব: ৭৭.৮ কোটি কিমি', 'এক বছর: প্রায় ১২ পার্থিব বছর', 'সবচেয়ে বড় গ্রহ · বিশাল ঝড় "গ্রেট রেড স্পট"'],
    facts: [['ব্যাস', '১,৩৯,৮২০ কিমি'], ['নিজ অক্ষে এক পাক', 'প্রায় ১০ ঘণ্টা'], ['এক বছর', 'প্রায় ১২ পার্থিব বছর'], ['উপগ্রহ', '৯০টিরও বেশি'], ['সূর্য থেকে দূরত্ব', '৭৭.৮ কোটি কিমি']],
    note: 'সবচেয়ে বড় গ্রহ — এর ভেতরে ১,৩০০টির মতো পৃথিবী এঁটে যাবে। "গ্রেট রেড স্পট" নামের ঝড়টি পৃথিবীর চেয়েও চওড়া।' },
  { key: 'saturn', bn: 'শনি', en: 'SATURN', color: '#e3cc95', r: 2.7, a: 64, T: 29.46, a0: 5.2, tilt: 0.47, spin: 0.8, ring: true,
    info: ['সূর্য থেকে দূরত্ব: ১৪৩ কোটি কিমি', 'এক বছর: প্রায় ২৯ পার্থিব বছর', 'বরফ ও পাথরের অপূর্ব বলয়'],
    facts: [['ব্যাস', '১,১৬,৪৬০ কিমি'], ['নিজ অক্ষে এক পাক', 'প্রায় সাড়ে ১০ ঘণ্টা'], ['এক বছর', 'প্রায় ২৯.৫ পার্থিব বছর'], ['উপগ্রহ', '২৭০টিরও বেশি'], ['সূর্য থেকে দূরত্ব', '১৪৩ কোটি কিমি']],
    note: 'বরফ আর পাথরের টুকরোয় গড়া অপূর্ব বলয়ের গ্রহ। গড় ঘনত্ব পানির চেয়েও কম।' },
  { key: 'uranus', bn: 'ইউরেনাস', en: 'URANUS', color: '#9fdfe6', r: 1.8, a: 78, T: 84, a0: 0.3, tilt: 1.71, spin: 0.6, atmo: [0.6, 0.9, 1],
    info: ['সূর্য থেকে দূরত্ব: ২৮৭ কোটি কিমি', 'এক বছর: প্রায় ৮৪ পার্থিব বছর', 'প্রায় ৯৮° কাত হয়ে গড়িয়ে চলে'],
    facts: [['ব্যাস', '৫০,৭২৪ কিমি'], ['নিজ অক্ষে এক পাক', '১৭ ঘণ্টা ১৪ মিনিট (উল্টো দিকে)'], ['এক বছর', 'প্রায় ৮৪ পার্থিব বছর'], ['উপগ্রহ', '২৮টিরও বেশি'], ['সূর্য থেকে দূরত্ব', '২৮৭ কোটি কিমি']],
    note: 'প্রায় ৯৮° কাত হয়ে পাশ ফিরে গড়িয়ে চলে। মিথেন গ্যাসের কারণে রং হালকা নীলচে-সবুজ।' },
  { key: 'neptune', bn: 'নেপচুন', en: 'NEPTUNE', color: '#5b7fe6', r: 1.7, a: 90, T: 164.8, a0: 2.6, tilt: 0.49, spin: 0.6, atmo: [0.4, 0.55, 1],
    info: ['সূর্য থেকে দূরত্ব: ৪৫০ কোটি কিমি', 'এক বছর: প্রায় ১৬৫ পার্থিব বছর', 'সবচেয়ে প্রবল বাতাস — ঘণ্টায় ২,০০০ কিমিরও বেশি'],
    facts: [['ব্যাস', '৪৯,২৪৪ কিমি'], ['নিজ অক্ষে এক পাক', '১৬ ঘণ্টা ৬ মিনিট'], ['এক বছর', 'প্রায় ১৬৫ পার্থিব বছর'], ['উপগ্রহ', 'অন্তত ১৬টি'], ['সূর্য থেকে দূরত্ব', '৪৫০ কোটি কিমি']],
    note: 'সূর্য থেকে সবচেয়ে দূরের গ্রহ। এখানে বয়ে যায় সৌরজগতের সবচেয়ে প্রবল বাতাস — ঘণ্টায় ২,০০০ কিমিরও বেশি।' },
];
PLANETS.forEach((p) => { p.w = 0.32 / Math.sqrt(p.T); });
const SUN_R = 6;
const SUN_INFO = { key: 'sun', bn: 'সূর্য', en: 'THE SUN', color: '#ffb547',
  info: ['একটি মাঝারি আকারের হলুদ নক্ষত্র', 'ব্যাস পৃথিবীর প্রায় ১০৯ গুণ', 'পৃষ্ঠের তাপমাত্রা প্রায় ৫,৫০০°সে'],
  facts: [['ব্যাস', 'প্রায় ১৩ লক্ষ ৯২ হাজার কিমি'], ['নিজ অক্ষে এক পাক', 'প্রায় ২৫ দিন (বিষুবরেখায়)'], ['পৃষ্ঠের তাপমাত্রা', 'প্রায় ৫,৫০০°সে'], ['বয়স', 'প্রায় ৪৬০ কোটি বছর'], ['আলো পৃথিবীতে পৌঁছায়', 'প্রায় ৮ মিনিট ২০ সেকেন্ডে']],
  note: 'একটি মাঝারি আকারের হলুদ নক্ষত্র। পুরো সৌরজগতের ভরের ৯৯.৮ ভাগই সূর্যের; ব্যাস পৃথিবীর প্রায় ১০৯ গুণ।' };
const BELT_R = 37, BELT = 9;
const BELT_INFO = { key: 'belt', bn: 'গ্রহাণুপুঞ্জ', en: 'ASTEROID BELT', color: '#9a8d7c',
  info: ['মঙ্গল আর বৃহস্পতির কক্ষপথের মাঝে', 'লাখ লাখ পাথুরে আর ধাতব টুকরো', 'সবচেয়ে বড় সদস্য: বামন গ্রহ সেরেস'],
  facts: [['অবস্থান', 'মঙ্গল আর বৃহস্পতির মাঝে'], ['সূর্য থেকে দূরত্ব', 'প্রায় ৩৩ থেকে ৪৮ কোটি কিমি'], ['সবচেয়ে বড় সদস্য', 'সেরেস — ব্যাস প্রায় ৯৪০ কিমি'], ['জানা গ্রহাণু', '১০ লক্ষেরও বেশি'], ['সব মিলিয়ে ভর', 'চাঁদের মাত্র ৩ ভাগের মতো']],
  note: 'সূর্যকে ঘিরে ঘুরছে লাখ লাখ পাথুরে আর ধাতব টুকরো — গ্রহ গড়ে ওঠার সময়কার বেঁচে যাওয়া উপাদান। ছবিতে যত ঘন দেখায় আসলে তত নয়: দুটো গ্রহাণুর মাঝে গড়ে প্রায় ১০ লক্ষ কিলোমিটার ফাঁকা।' };
/* index: 0 the Sun, 1..8 the planets, 9 the belt. ORDER is outward from the Sun,
   for the chips and the card's previous / next. */
const BODIES = [SUN_INFO, ...PLANETS, BELT_INFO];
const ORDER = [0, 1, 2, 3, 4, BELT, 5, 6, 7, 8];
const isPlanet = (i) => i >= 1 && i <= 8;

/* the English edition's words for the same bodies */
const EN_DATA = {
  sun: { name: 'The Sun', info: ['A middle-sized yellow star', 'About 109 times as wide as Earth', 'Surface temperature about 5,500 °C'],
    facts: [['Diameter', 'about 1.39 million km'], ['One spin', 'about 25 days (at the equator)'], ['Surface temperature', 'about 5,500 °C'], ['Age', 'about 4.6 billion years'], ['Light reaches Earth in', 'about 8 min 20 s']],
    note: "A middle-sized yellow star. It holds 99.8% of the Solar System's mass and is about 109 times as wide as Earth." },
  mercury: { name: 'Mercury', info: ['Distance from the Sun: 57.9 million km', 'One year: just 88 days', 'The smallest planet in the Solar System'],
    facts: [['Diameter', '4,879 km'], ['One spin', 'about 59 days'], ['One year', '88 days'], ['Moons', 'none'], ['Distance from the Sun', '57.9 million km']],
    note: 'The smallest planet and the closest to the Sun. With almost no air, its days are scorching and its nights freezing.' },
  venus: { name: 'Venus', info: ['Distance from the Sun: 108 million km', 'One year: 225 days', 'The hottest planet — about 465 °C'],
    facts: [['Diameter', '12,104 km'], ['One spin', '243 days (backwards)'], ['One year', '225 days'], ['Moons', 'none'], ['Distance from the Sun', '108 million km']],
    note: 'Wrapped in thick clouds of carbon dioxide, it is the hottest planet — about 465 °C. A day here lasts longer than its year.' },
  earth: { name: 'Earth', info: ['Distance from the Sun: 150 million km', 'One year: 365 days', 'The only known home of life · one Moon'],
    facts: [['Diameter', '12,742 km'], ['One spin', '23 h 56 min'], ['One year', '365.25 days'], ['Moons', '1 — the Moon'], ['Distance from the Sun', '150 million km']],
    note: 'The only known home of life. City lights glow on the night side, and about 71% of the surface is water.' },
  mars: { name: 'Mars', info: ['Distance from the Sun: 228 million km', 'One year: 687 days', 'The red planet — dusted with iron rust'],
    facts: [['Diameter', '6,779 km'], ['One spin', '24 h 37 min'], ['One year', '687 days'], ['Moons', '2 — Phobos and Deimos'], ['Distance from the Sun', '228 million km']],
    note: 'The red planet, covered in rust-coloured dust. Olympus Mons, the tallest volcano in the Solar System, is here.' },
  jupiter: { name: 'Jupiter', info: ['Distance from the Sun: 778 million km', 'One year: about 12 Earth years', 'The largest planet · the giant storm "Great Red Spot"'],
    facts: [['Diameter', '139,820 km'], ['One spin', 'about 10 hours'], ['One year', 'about 12 Earth years'], ['Moons', 'more than 90'], ['Distance from the Sun', '778 million km']],
    note: 'The largest planet — about 1,300 Earths would fit inside. The Great Red Spot is a storm wider than Earth.' },
  saturn: { name: 'Saturn', info: ['Distance from the Sun: 1.43 billion km', 'One year: about 29 Earth years', 'Stunning rings of ice and rock'],
    facts: [['Diameter', '116,460 km'], ['One spin', 'about 10.5 hours'], ['One year', 'about 29.5 Earth years'], ['Moons', 'more than 270'], ['Distance from the Sun', '1.43 billion km']],
    note: 'The planet of the glorious rings, made of chunks of ice and rock. On average it is less dense than water.' },
  uranus: { name: 'Uranus', info: ['Distance from the Sun: 2.87 billion km', 'One year: about 84 Earth years', 'Tilted about 98° — it rolls on its side'],
    facts: [['Diameter', '50,724 km'], ['One spin', '17 h 14 min (backwards)'], ['One year', 'about 84 Earth years'], ['Moons', 'more than 28'], ['Distance from the Sun', '2.87 billion km']],
    note: 'Tipped over by about 98°, it rolls along on its side. Methane gas gives it its pale blue-green colour.' },
  belt: { name: 'Asteroid Belt', info: ['Between the orbits of Mars and Jupiter', 'Millions of fragments of rock and metal', 'Largest member: the dwarf planet Ceres'],
    facts: [['Where', 'between Mars and Jupiter'], ['Distance from the Sun', 'about 330–480 million km'], ['Largest member', 'Ceres — about 940 km across'], ['Known asteroids', 'more than a million'], ['Total mass', "only about 3% of the Moon's"]],
    note: 'Millions of fragments of rock and metal circle the Sun here, left over from when the planets formed. It is far emptier than it looks: neighbouring asteroids are on average about a million kilometres apart.' },
  neptune: { name: 'Neptune', info: ['Distance from the Sun: 4.5 billion km', 'One year: about 165 Earth years', 'The fiercest winds — over 2,000 km/h'],
    facts: [['Diameter', '49,244 km'], ['One spin', '16 h 6 min'], ['One year', 'about 165 Earth years'], ['Moons', 'at least 16'], ['Distance from the Sun', '4.5 billion km']],
    note: 'The farthest planet from the Sun, with the fiercest winds in the Solar System — over 2,000 km an hour.' },
};
/* name, the other-language name, caption lines, card facts and note, in the page's language */
function txt(d) {
  if (!EN) return { name: d.bn, alt: d.en, altLang: 'en', info: d.info, facts: d.facts, note: d.note };
  const e = EN_DATA[d.key];
  return { name: e.name, alt: d.bn, altLang: 'bn', info: e.info, facts: e.facts, note: e.note };
}
const T = EN ? {
  hintTouch: 'Drag to turn · pinch to zoom · tap a planet', hintMouse: 'Drag to turn · scroll to zoom · click a planet',
  realTime: 'Real time', realTitle: 'The real view right now — from where you are', you: 'You are here', fastHint: 'Press ⏩ to set the planets moving again', realHint: 'See Earth right now — from where you are',
  showInfo: ' — show facts', centre: 'The centre of the Solar System', between: 'Between Mars and Jupiter', atCentre: 'At the centre',
  planetOf: (i) => `Planet ${i} / 8 · ${['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth'][i - 1]} from the Sun`,
  planetN: (i) => `Planet ${i} / 8`, closeCard: 'Close the facts', play: 'Play', pause: 'Pause',
  frame: (f) => `Frame ${f} / ${TOTAL}`, loading: (p) => `Building the 3D Solar System… ${p}%`,
} : {
  hintTouch: 'আঙুলে টেনে ঘোরান · দুই আঙুলে জুম · গ্রহে ট্যাপ করুন', hintMouse: 'মাউস টেনে ঘোরান · স্ক্রল করে জুম · গ্রহে ক্লিক করুন',
  realTime: 'রিয়েল টাইম', realTitle: 'এই মুহূর্তের আসল দৃশ্য — আপনার অবস্থান থেকে', you: 'আপনি এখানে', fastHint: 'গ্রহগুলোকে আবার ঘোরাতে ⏩ চাপুন', realHint: 'দেখুন এই মুহূর্তের পৃথিবী — আপনি যেখানে আছেন',
  showInfo: ' — তথ্য দেখুন', centre: 'সৌরজগতের কেন্দ্র', between: 'মঙ্গল আর বৃহস্পতির মাঝে', atCentre: 'কেন্দ্রে',
  planetOf: (i) => `গ্রহ ${bn(i)} / ${bn(8)} · সূর্য থেকে ${['প্রথম', 'দ্বিতীয়', 'তৃতীয়', 'চতুর্থ', 'পঞ্চম', 'ষষ্ঠ', 'সপ্তম', 'অষ্টম'][i - 1]}`,
  planetN: (i) => `গ্রহ ${bn(i)} / ${bn(8)}`, closeCard: 'তথ্য বন্ধ করুন', play: 'চালু করুন', pause: 'বিরতি',
  frame: (f) => `ফ্রেম ${bn(f)} / ${bn(TOTAL)}`, loading: (p) => `থ্রিডি সৌরজগৎ তৈরি হচ্ছে… ${bn(p)}%`,
};

const SHOTS = [
  { type: 'wide0', s: 0, e: 7 },
  { type: 'sun', s: 7, e: 14 },
  ...PLANETS.map((p, i) => ({ type: 'planet', i, s: 14 + i * 6.375, e: 14 + (i + 1) * 6.375 })),
  { type: 'wide1', s: 65, e: 75 },
];
const shotAt = (t) => { for (let j = SHOTS.length - 1; j >= 0; j--) if (t >= SHOTS[j].s) return j; return 0; };

Object.assign(T, EN
  ? { moonOf: (p) => `Moon of ${p}`, relMoons: 'Moons', relHere: 'Out here', relBack: 'Back to' }
  : { moonOf: (p) => `${genitive(p)} চাঁদ`, relMoons: 'চাঁদ', relHere: 'এখানে আছে', relBack: 'ফিরে যান' });
/* Bengali possessive: পৃথিবী → পৃথিবীর, মঙ্গল → মঙ্গলের */
function genitive(w) { return /[া-ৌঅ-ঔ]$/.test(w) ? w + 'র' : w + 'ের'; }

/* ---------------- beyond the planets: moons, dwarf planets, the edges of the Solar System ----------------
   Distances out to Neptune keep the layout above; beyond it they are squeezed on a logarithm,
   or the Oort cloud would sit thousands of Neptunes away. Small moons are drawn larger than
   they are, so they can be seen at all; the cards give the real figures. */
const AU_ANCHORS = [[0, 6.5], [0.39, 13], [0.72, 18], [1, 24], [1.52, 31], [2.77, 37], [5.2, 48], [9.54, 64], [19.2, 78], [30.07, 90]];
function auToScene(au) {
  if (au >= 30.07) return 90 + 50 * Math.log(au / 30.07);
  for (let i = 1; i < AU_ANCHORS.length; i++) {
    const [a1, s1] = AU_ANCHORS[i];
    if (au <= a1) { const [a0, s0] = AU_ANCHORS[i - 1]; return s0 + (s1 - s0) * (au - a0) / (a1 - a0); }
  }
  return 90;
}
/* a direction from ecliptic longitude and latitude, in the frame the planets move in */
function eclDir(lon, lat, out = new THREE.Vector3()) {
  const l = lon * Math.PI / 180, b = lat * Math.PI / 180;
  return out.set(Math.cos(b) * Math.cos(l), Math.sin(b), -Math.cos(b) * Math.sin(l));
}
SUN_INFO.kind = 'sun'; BELT_INFO.kind = 'belt';
PLANETS.forEach((p) => { p.kind = 'planet'; });
const KUIPER = 10, HELIO = 11, OORT = 12;
const HELIO_NOSE = eclDir(255.7, 5.1);   // the interstellar wind blows in from here (IBEX)

/* f: facts (Bengali), n: note; the English is in EN_X below */
const EXTRAS = [
  { key: 'kuiper', kind: 'region', bn: 'কাইপার বেল্ট', en: 'KUIPER BELT', color: '#9fb3d9', kick: 'নেপচুনের ওপারে',
    f: [['অবস্থান', 'সূর্য থেকে ৩০–৫০ AU (৪৫০–৭৫০ কোটি কিমি)'], ['বড় সদস্য', 'প্লুটো, হাউমেয়া, মাকেমাকে'], ['১০০ কিমির বড় বস্তু', 'আনুমানিক এক লাখেরও বেশি'], ['কাছ থেকে দেখা', 'আরোকথ — নিউ হরাইজনস, ২০১৯']],
    n: 'নেপচুনের ওপারে বরফের টুকরোর এক বিশাল বলয় — গ্রহাণুপুঞ্জের মতো, কিন্তু প্রায় ২০ গুণ চওড়া আর বহু গুণ ভারী। স্বল্পমেয়াদি অনেক ধূমকেতু এখান থেকেই আসে।',
    rel: ['pluto', 'haumea', 'makemake', 'eris'] },
  { key: 'helio', kind: 'region', bn: 'হেলিওস্ফিয়ার', en: 'HELIOSPHERE', color: '#a98cf0', kick: 'সূর্যের বাতাসের বুদবুদ',
    f: [['যা দিয়ে গড়া', 'সৌরবায়ু — সূর্য থেকে ছুটে আসা কণার স্রোত'], ['টার্মিনেশন শক', 'প্রায় ৯০ AU — বাতাস এখানে হঠাৎ ধীর হয়'], ['হেলিওপজ', 'প্রায় ১২০ AU — এর বাইরে নক্ষত্রের মাঝের জায়গা'], ['পেরিয়ে গেছে', 'ভয়েজার ১ (২০১২), ভয়েজার ২ (২০১৮)']],
    n: 'সৌরবায়ু ছায়াপথের গ্যাসকে ঠেলে সরিয়ে বিশাল এক বুদবুদ গড়েছে, আর সব গ্রহ তার ভেতরে। ক্ষতিকর মহাজাগতিক রশ্মির বড় অংশ এটাই আটকায়। এর আসল আকার নিয়ে বিজ্ঞানীদের বিতর্ক আছে — এখানে দেখানো হয়েছে ধূমকেতুর মতো লেজওয়ালা মডেল।',
    rel: ['voyager1', 'voyager2'] },
  { key: 'oort', kind: 'region', bn: 'ঊর্ট মেঘ', en: 'OORT CLOUD', color: '#8b9bc0', kick: 'সৌরজগতের শেষ সীমা',
    f: [['দূরত্ব', 'আনুমানিক ২,০০০ থেকে ১,০০,০০০ AU'], ['বাইরের কিনারা', 'প্রায় ১.৬ আলোকবর্ষ দূরে'], ['বস্তুর সংখ্যা', 'শত শত কোটি, হয়তো লক্ষ কোটি বরফের টুকরো'], ['দেখা গেছে কি', 'না — এখনো শুধু অনুমান']],
    n: 'সৌরজগৎকে ঘিরে থাকা বরফের এক অনুমিত গোলক; দীর্ঘমেয়াদি ধূমকেতুগুলো এখান থেকে আসে বলে ধারণা। ভয়েজার ১-এর এর ভেতরের কিনারায় পৌঁছাতে প্রায় ৩০০ বছর লাগবে, আর পেরোতে প্রায় ৩০,০০০ বছর।' },

  { key: 'ceres', kind: 'dwarf', bn: 'সেরেস', en: 'CERES', color: '#9a958d', kick: 'বামন গ্রহ · গ্রহাণুপুঞ্জে', home: 'belt',
    orbit: { a: 2.77, e: 0.076, i: 10.6, node: 80.3, peri: 73.6, T: 4.6, a0: 2.4 }, r: 0.18, paint: 'ceres',
    f: [['ব্যাস', 'প্রায় ৯৪০ কিমি'], ['সূর্য থেকে দূরত্ব', 'প্রায় ৪১ কোটি কিমি (২.৮ AU)'], ['এক বছর', 'প্রায় ৪.৬ পার্থিব বছর'], ['উপগ্রহ', 'নেই']],
    n: 'গ্রহাণুপুঞ্জের সবচেয়ে বড় সদস্য, আর ভেতরের সৌরজগতের একমাত্র বামন গ্রহ। অকাটর খাদে উজ্জ্বল লবণের দাগ দেখা যায়; ডন যান ২০১৫ সাল থেকে একে ঘিরে ঘুরেছে।' },
  { key: 'pluto', kind: 'dwarf', bn: 'প্লুটো', en: 'PLUTO', color: '#c9a58e', kick: 'বামন গ্রহ · কাইপার বেল্টে', home: 'kuiper',
    orbit: { a: 39.48, e: 0.249, i: 17.2, node: 110.3, peri: 113.8, T: 248, a0: 4.1 }, r: 0.3, tex: 'pluto', paint: 'icy', tint: [205, 175, 155],
    f: [['ব্যাস', '২,৩৭৭ কিমি'], ['সূর্য থেকে গড় দূরত্ব', 'প্রায় ৫৯০ কোটি কিমি (৩৯.৫ AU)'], ['এক বছর', 'প্রায় ২৪৮ পার্থিব বছর'], ['উপগ্রহ', '৫টি — সবচেয়ে বড়টি শ্যারন']],
    n: '২০০৬ সাল থেকে বামন গ্রহ। বুকে হৃদয়ের মতো দেখতে নাইট্রোজেন বরফের বিশাল সমভূমি; নিউ হরাইজনস ২০১৫ সালে পাশ দিয়ে উড়ে গিয়ে প্রথম কাছের ছবি তোলে। দক্ষিণের যে অংশের ছবি তোলা যায়নি, সেটা আশপাশের রঙে ভরাট করা।' },
  { key: 'eris', kind: 'dwarf', bn: 'এরিস', en: 'ERIS', color: '#e6e6e2', kick: 'বামন গ্রহ · কাইপার বেল্টের বাইরে', home: 'kuiper',
    orbit: { a: 67.9, e: 0.436, i: 44.0, node: 35.9, peri: 151.6, T: 559, a0: 1.3 }, r: 0.29, paint: 'icy', tint: [236, 234, 228],
    f: [['ব্যাস', '২,৩২৬ কিমি'], ['সূর্য থেকে গড় দূরত্ব', 'প্রায় ১,০১৫ কোটি কিমি (৬৮ AU)'], ['এক বছর', 'প্রায় ৫৫৯ পার্থিব বছর'], ['উপগ্রহ', '১টি — ডিসনোমিয়া']],
    n: '২০০৫ সালে এর আবিষ্কারের পরেই প্রশ্ন ওঠে প্লুটোকে আর গ্রহ বলা যায় কিনা। ভরে প্লুটোর চেয়ে প্রায় ২৭ ভাগ বেশি। কোনো যান এর কাছে যায়নি — রূপটা শিল্পীর কল্পনা।' },
  { key: 'haumea', kind: 'dwarf', bn: 'হাউমেয়া', en: 'HAUMEA', color: '#ece8e4', kick: 'বামন গ্রহ · কাইপার বেল্টে', home: 'kuiper',
    orbit: { a: 43.1, e: 0.195, i: 28.2, node: 122.2, peri: 239, T: 284, a0: 5.6 }, r: 0.26, shape: [1.25, 1.0, 0.64], paint: 'haumea',
    f: [['আকার', 'প্রায় ২,১০০ × ১,৬৮০ × ১,০৭০ কিমি'], ['সূর্য থেকে গড় দূরত্ব', 'প্রায় ৬৪৫ কোটি কিমি (৪৩ AU)'], ['এক বছর', 'প্রায় ২৮৪ পার্থিব বছর'], ['উপগ্রহ', '২টি']],
    n: 'মাত্র ৪ ঘণ্টায় একবার নিজ অক্ষে ঘোরে — এত জোরে যে ডিমের মতো লম্বাটে হয়ে গেছে। চারপাশে একটা সরু বলয়ও আছে। রূপটা শিল্পীর কল্পনা।' },
  { key: 'makemake', kind: 'dwarf', bn: 'মাকেমাকে', en: 'MAKEMAKE', color: '#c98d6c', kick: 'বামন গ্রহ · কাইপার বেল্টে', home: 'kuiper',
    orbit: { a: 45.4, e: 0.16, i: 29.0, node: 79.4, peri: 297, T: 306, a0: 0.4 }, r: 0.22, paint: 'icy', tint: [205, 142, 108],
    f: [['ব্যাস', 'প্রায় ১,৪৩০ কিমি'], ['সূর্য থেকে গড় দূরত্ব', 'প্রায় ৬৮০ কোটি কিমি (৪৫.৫ AU)'], ['এক বছর', 'প্রায় ৩০৬ পার্থিব বছর'], ['উপগ্রহ', '১টি']],
    n: '২০০৫ সালে ইস্টারের ঠিক পরে আবিষ্কৃত, তাই নাম রাখা হয়েছে ইস্টার দ্বীপের সৃষ্টিদেবতার নামে। লালচে মিথেন বরফে ঢাকা। রূপটা শিল্পীর কল্পনা।' },

  // moons: parent, radius and orbit in scene units, period in days (minus: backwards), inclination in degrees
  { key: 'moon', kind: 'moon', parent: 'earth', bn: 'চাঁদ', en: 'THE MOON', color: '#b9b6b0', r: 0.27, existing: true,
    f: [['ব্যাস', '৩,৪৭৪ কিমি'], ['পৃথিবী থেকে দূরত্ব', '৩,৮৪,৪০০ কিমি'], ['এক পাক ঘুরতে লাগে', '২৭.৩ দিন'], ['মানুষের পা পড়েছে', '১৯৬৯ থেকে ১৯৭২, ১২ জন']],
    n: 'পৃথিবীর একমাত্র প্রাকৃতিক উপগ্রহ। নিজ অক্ষে আর পৃথিবীর চারপাশে একই সময়ে ঘোরে বলে আমরা সব সময় এর একটা দিকই দেখি।' },
  { key: 'phobos', kind: 'moon', parent: 'mars', bn: 'ফোবস', en: 'PHOBOS', color: '#8a7b6d', r: 0.06, orbit: 1.25, P: 0.32, inc: 1, a0: 0.3, tex: 'phobos', paint: 'rocky', tint: [150, 130, 112],
    f: [['ব্যাস', 'প্রায় ২২ কিমি'], ['মঙ্গল থেকে দূরত্ব', '৯,৩৭৬ কিমি'], ['এক পাক ঘুরতে লাগে', '৭ ঘণ্টা ৩৯ মিনিট'], ['আবিষ্কার', '১৮৭৭ — আসাফ হল']],
    n: 'আলুর মতো এবড়োখেবড়ো ছোট্ট চাঁদ, মঙ্গলের খুব কাছ দিয়ে ঘোরে আর ধীরে ধীরে আরও কাছে নেমে আসছে — কয়েক কোটি বছর পর ভেঙে টুকরো হয়ে যাবে।' },
  { key: 'deimos', kind: 'moon', parent: 'mars', bn: 'ডিমোস', en: 'DEIMOS', color: '#9a8a7a', r: 0.045, orbit: 1.9, P: 1.26, inc: 2, a0: 2.2, paint: 'rocky', tint: [160, 142, 124],
    f: [['ব্যাস', 'প্রায় ১২ কিমি'], ['মঙ্গল থেকে দূরত্ব', '২৩,৪৬৩ কিমি'], ['এক পাক ঘুরতে লাগে', '৩০ ঘণ্টা ১৮ মিনিট'], ['আবিষ্কার', '১৮৭৭ — আসাফ হল']],
    n: 'মঙ্গলের দুই চাঁদের মধ্যে ছোট আর দূরেরটি; পৃষ্ঠ ধুলোয় ঢাকা, তাই তুলনায় মসৃণ। রূপটা আঁকা।' },
  { key: 'io', kind: 'moon', parent: 'jupiter', bn: 'আইও', en: 'IO', color: '#d9c25a', r: 0.17, orbit: 5.2, P: 1.77, inc: 0, a0: 0.5, tex: 'io', paint: 'rocky', tint: [220, 200, 110],
    f: [['ব্যাস', '৩,৬৪৩ কিমি'], ['বৃহস্পতি থেকে দূরত্ব', '৪,২১,৭০০ কিমি'], ['এক পাক ঘুরতে লাগে', '১.৭৭ দিন'], ['আবিষ্কার', '১৬১০ — গ্যালিলিও']],
    n: 'সৌরজগতের সবচেয়ে আগ্নেয় জায়গা — চার শতাধিক জীবন্ত আগ্নেয়গিরি। বৃহস্পতির টানে ভেতরটা ক্রমাগত দলে-মুচড়ে গরম হয়।' },
  { key: 'europa', kind: 'moon', parent: 'jupiter', bn: 'ইউরোপা', en: 'EUROPA', color: '#cdbfa6', r: 0.15, orbit: 6.6, P: 3.55, inc: 0.5, a0: 2.5, tex: 'europa', paint: 'icy', tint: [215, 205, 190],
    f: [['ব্যাস', '৩,১২২ কিমি'], ['বৃহস্পতি থেকে দূরত্ব', '৬,৭১,১০০ কিমি'], ['এক পাক ঘুরতে লাগে', '৩.৫৫ দিন'], ['আবিষ্কার', '১৬১০ — গ্যালিলিও']],
    n: 'বরফের খোলসের নিচে লোনা পানির বিশাল সাগর — প্রাণের খোঁজে বিজ্ঞানীদের সবচেয়ে আশার জায়গাগুলোর একটি।' },
  { key: 'ganymede', kind: 'moon', parent: 'jupiter', bn: 'গ্যানিমিড', en: 'GANYMEDE', color: '#a59a8c', r: 0.24, orbit: 8.4, P: 7.15, inc: 0.2, a0: 4.3, tex: 'ganymede', paint: 'rocky', tint: [170, 160, 148],
    f: [['ব্যাস', '৫,২৬৮ কিমি'], ['বৃহস্পতি থেকে দূরত্ব', '১০,৭০,৪০০ কিমি'], ['এক পাক ঘুরতে লাগে', '৭.১৫ দিন'], ['আবিষ্কার', '১৬১০ — গ্যালিলিও']],
    n: 'সৌরজগতের সবচেয়ে বড় চাঁদ — বুধের চেয়েও বড়। একমাত্র চাঁদ যার নিজের চৌম্বকক্ষেত্র আছে।' },
  { key: 'callisto', kind: 'moon', parent: 'jupiter', bn: 'ক্যালিস্টো', en: 'CALLISTO', color: '#6f665d', r: 0.22, orbit: 10.8, P: 16.7, inc: 0.3, a0: 1.1, tex: 'callisto', paint: 'rocky', tint: [118, 108, 98],
    f: [['ব্যাস', '৪,৮২১ কিমি'], ['বৃহস্পতি থেকে দূরত্ব', '১৮,৮২,৭০০ কিমি'], ['এক পাক ঘুরতে লাগে', '১৬.৭ দিন'], ['আবিষ্কার', '১৬১০ — গ্যালিলিও']],
    n: 'সৌরজগতের সবচেয়ে বেশি খাদে ভরা পৃষ্ঠ — কোটি কোটি বছর ধরে প্রায় অপরিবর্তিত। মানচিত্রের যে অংশের ছবি নেই, সেটা ভরাট করা।' },
  { key: 'enceladus', kind: 'moon', parent: 'saturn', bn: 'এনসেলাডাস', en: 'ENCELADUS', color: '#eef2f4', r: 0.07, orbit: 7.0, P: 1.37, inc: 0, a0: 3.0, tex: 'enceladus', paint: 'icy', tint: [240, 244, 246],
    f: [['ব্যাস', '৫০৪ কিমি'], ['শনি থেকে দূরত্ব', '২,৩৮,০০০ কিমি'], ['এক পাক ঘুরতে লাগে', '১.৩৭ দিন'], ['আবিষ্কার', '১৭৮৯ — উইলিয়াম হার্শেল']],
    n: 'দক্ষিণ মেরুর ফাটল দিয়ে পানির বরফের ফোয়ারা মহাকাশে ছিটকে বেরোয় — নিচে লুকানো আছে এক সাগর।' },
  { key: 'rhea', kind: 'moon', parent: 'saturn', bn: 'রিয়া', en: 'RHEA', color: '#c9c6bf', r: 0.1, orbit: 8.6, P: 4.52, inc: 0.3, a0: 0.6, tex: 'rhea', paint: 'icy', tint: [205, 202, 195],
    f: [['ব্যাস', '১,৫২৭ কিমি'], ['শনি থেকে দূরত্ব', '৫,২৭,১০০ কিমি'], ['এক পাক ঘুরতে লাগে', '৪.৫২ দিন'], ['আবিষ্কার', '১৬৭২ — জোভান্নি ক্যাসিনি']],
    n: 'শনির দ্বিতীয় বৃহত্তম চাঁদ, প্রায় পুরোটাই বরফ আর খাদে ভরা।' },
  { key: 'titan', kind: 'moon', parent: 'saturn', bn: 'টাইটান', en: 'TITAN', color: '#d79a4e', r: 0.24, orbit: 11.5, P: 15.95, inc: 0.3, a0: 5.0, paint: 'titan', atmo: [1.0, 0.62, 0.25],
    f: [['ব্যাস', '৫,১৫০ কিমি'], ['শনি থেকে দূরত্ব', '১২,২১,৯০০ কিমি'], ['এক পাক ঘুরতে লাগে', '১৫.৯৫ দিন'], ['আবিষ্কার', '১৬৫৫ — ক্রিস্টিয়ান হাইগেনস']],
    n: 'ঘন নাইট্রোজেনের বায়ুমণ্ডলে ঢাকা কমলা ধোঁয়াশার জগৎ; পৃষ্ঠে মিথেনের হ্রদ আর নদী। হাইগেনস যান ২০০৫ সালে এখানে নেমেছিল।' },
  { key: 'iapetus', kind: 'moon', parent: 'saturn', bn: 'আয়াপেটাস', en: 'IAPETUS', color: '#8f877c', r: 0.1, orbit: 15.0, P: 79.3, inc: 8, a0: 2.0, tex: 'iapetus', paint: 'rocky', tint: [150, 140, 128],
    f: [['ব্যাস', '১,৪৬৯ কিমি'], ['শনি থেকে দূরত্ব', '৩৫,৬০,৮০০ কিমি'], ['এক পাক ঘুরতে লাগে', '৭৯.৩ দিন'], ['আবিষ্কার', '১৬৭১ — জোভান্নি ক্যাসিনি']],
    n: 'দুই রঙের চাঁদ — এক দিক কয়লার মতো কালো, অন্য দিক বরফের মতো সাদা। বিষুবরেখা বরাবর পাহাড়ের এক লম্বা শিরদাঁড়া।' },
  { key: 'miranda', kind: 'moon', parent: 'uranus', bn: 'মিরান্ডা', en: 'MIRANDA', color: '#b9b9b6', r: 0.055, orbit: 3.0, P: 1.41, inc: 4, a0: 0.8, tex: 'miranda', paint: 'icy', tint: [190, 190, 186],
    f: [['ব্যাস', '৪৭২ কিমি'], ['ইউরেনাস থেকে দূরত্ব', '১,২৯,৯০০ কিমি'], ['এক পাক ঘুরতে লাগে', '১.৪১ দিন'], ['আবিষ্কার', '১৯৪৮ — জেরার্ড কাইপার']],
    n: 'জোড়াতালি দেওয়া পৃষ্ঠ; এখানে আছে প্রায় ২০ কিমি উঁচু খাড়া পাহাড় ভেরোনা রুপেস। ভয়েজার ২ শুধু দক্ষিণ দিকের ছবি তুলেছিল — বাকিটা ভরাট করা।' },
  { key: 'ariel', kind: 'moon', parent: 'uranus', bn: 'এরিয়েল', en: 'ARIEL', color: '#c7c7c3', r: 0.08, orbit: 3.8, P: 2.52, inc: 0, a0: 2.9, tex: 'ariel', paint: 'icy', tint: [200, 200, 196],
    f: [['ব্যাস', '১,১৫৮ কিমি'], ['ইউরেনাস থেকে দূরত্ব', '১,৯০,৯০০ কিমি'], ['এক পাক ঘুরতে লাগে', '২.৫২ দিন'], ['আবিষ্কার', '১৮৫১ — উইলিয়াম ল্যাসেল']],
    n: 'ইউরেনাসের চাঁদগুলোর মধ্যে সবচেয়ে উজ্জ্বল আর সবচেয়ে নবীন পৃষ্ঠ। উত্তরের অংশ ভরাট করা।' },
  { key: 'titania', kind: 'moon', parent: 'uranus', bn: 'টাইটানিয়া', en: 'TITANIA', color: '#b7b2ab', r: 0.1, orbit: 5.2, P: 8.71, inc: 0, a0: 4.7, tex: 'titania', paint: 'icy', tint: [188, 182, 175],
    f: [['ব্যাস', '১,৫৭৭ কিমি'], ['ইউরেনাস থেকে দূরত্ব', '৪,৩৬,৩০০ কিমি'], ['এক পাক ঘুরতে লাগে', '৮.৭১ দিন'], ['আবিষ্কার', '১৭৮৭ — উইলিয়াম হার্শেল']],
    n: 'ইউরেনাসের সবচেয়ে বড় চাঁদ; বিশাল গিরিখাতে চেরা। উত্তরের অংশ ভরাট করা।' },
  { key: 'oberon', kind: 'moon', parent: 'uranus', bn: 'ওবেরন', en: 'OBERON', color: '#a29a91', r: 0.1, orbit: 6.4, P: 13.46, inc: 0, a0: 1.6, tex: 'oberon', paint: 'rocky', tint: [165, 156, 147],
    f: [['ব্যাস', '১,৫২৩ কিমি'], ['ইউরেনাস থেকে দূরত্ব', '৫,৮৩,৫০০ কিমি'], ['এক পাক ঘুরতে লাগে', '১৩.৪৬ দিন'], ['আবিষ্কার', '১৭৮৭ — উইলিয়াম হার্শেল']],
    n: 'ইউরেনাসের সবচেয়ে বাইরের বড় চাঁদ; পুরোনো, খাদে ভরা পৃষ্ঠ। উত্তরের অংশ ভরাট করা।' },
  { key: 'triton', kind: 'moon', parent: 'neptune', bn: 'ট্রাইটন', en: 'TRITON', color: '#d8cbbf', r: 0.15, orbit: 4.0, P: -5.88, inc: 23, a0: 3.4, tex: 'triton', paint: 'icy', tint: [222, 210, 198],
    f: [['ব্যাস', '২,৭০৭ কিমি'], ['নেপচুন থেকে দূরত্ব', '৩,৫৪,৮০০ কিমি'], ['এক পাক ঘুরতে লাগে', '৫.৮৮ দিন — উল্টো দিকে'], ['আবিষ্কার', '১৮৪৬ — উইলিয়াম ল্যাসেল']],
    n: 'গ্রহের উল্টো দিকে ঘোরে — সম্ভবত একসময় কাইপার বেল্টের বস্তু ছিল, নেপচুনের টানে ধরা পড়েছে। নাইট্রোজেনের ফোয়ারা ওঠে; পৃষ্ঠ প্রায় −২৩৫°সে। উত্তরের অংশ ভরাট করা।' },
  { key: 'charon', kind: 'moon', parent: 'pluto', bn: 'শ্যারন', en: 'CHARON', color: '#a8a39e', r: 0.15, orbit: 0.95, P: 6.39, inc: 0, a0: 0, tex: 'charon', paint: 'icy', tint: [175, 170, 165],
    f: [['ব্যাস', '১,২১২ কিমি'], ['প্লুটো থেকে দূরত্ব', '১৯,৬০০ কিমি'], ['এক পাক ঘুরতে লাগে', '৬.৩৯ দিন'], ['আবিষ্কার', '১৯৭৮ — জেমস ক্রিস্টি']],
    n: 'প্লুটোর প্রায় অর্ধেক মাপের চাঁদ; দুজনেই সব সময় পরস্পরের দিকে একই মুখ ফিরিয়ে ঘোরে। উত্তর মেরুর লালচে দাগটার ডাকনাম মর্ডর। দক্ষিণের অংশ ভরাট করা।' },

  { key: 'voyager1', kind: 'craft', bn: 'ভয়েজার ১', en: 'VOYAGER 1', color: '#e8e2cf', kick: 'মহাকাশযান · নক্ষত্রের মাঝের জায়গায়', home: 'helio',
    dir: [255.9, 34.9], au: 170,
    f: [['উৎক্ষেপণ', '৫ সেপ্টেম্বর ১৯৭৭'], ['সূর্য থেকে দূরত্ব', 'প্রায় ১৭০ AU (২০২৬) — প্রায় ২,৫০০ কোটি কিমি'], ['হেলিওপজ পেরোয়', 'আগস্ট ২০১২, ১২১.৬ AU-তে'], ['সংকেত পৌঁছাতে লাগে', 'প্রায় ২৩ ঘণ্টা']],
    n: 'মানুষের তৈরি সবচেয়ে দূরের বস্তু। বৃহস্পতি আর শনির পাশ দিয়ে গিয়ে এখন নক্ষত্রের মাঝের জায়গায়; সঙ্গে আছে পৃথিবীর শব্দ আর ছবির সোনালি রেকর্ড। যানটা প্রতীকী, মাপমতো নয়।' },
  { key: 'voyager2', kind: 'craft', bn: 'ভয়েজার ২', en: 'VOYAGER 2', color: '#e8e2cf', kick: 'মহাকাশযান · নক্ষত্রের মাঝের জায়গায়', home: 'helio',
    dir: [290.5, -35.8], au: 145,
    f: [['উৎক্ষেপণ', '২০ আগস্ট ১৯৭৭'], ['সূর্য থেকে দূরত্ব', 'প্রায় ১৪৫ AU (২০২৬) — প্রায় ২,১৭০ কোটি কিমি'], ['হেলিওপজ পেরোয়', 'নভেম্বর ২০১৮, প্রায় ১১৯ AU-তে'], ['যা আর কেউ পারেনি', 'ইউরেনাস আর নেপচুনের পাশ দিয়ে যাওয়া']],
    n: 'চারটি দানব গ্রহের পাশ দিয়েই গেছে একমাত্র এই যান; ইউরেনাস (১৯৮৬) আর নেপচুনের (১৯৮৯) কাছের প্রায় সব ছবিই এর তোলা। যানটা প্রতীকী, মাপমতো নয়।' },
];

const EN_X = {
  kuiper: { name: 'Kuiper Belt', kick: 'Beyond Neptune', facts: [['Where', '30–50 AU from the Sun (4.5–7.5 billion km)'], ['Largest members', 'Pluto, Haumea, Makemake'], ['Bodies over 100 km', 'probably more than 100,000'], ['Seen up close', 'Arrokoth — New Horizons, 2019']],
    note: 'A vast ring of icy bodies beyond Neptune — like the asteroid belt, but about 20 times as wide and many times as massive. Many short-period comets come from here.' },
  helio: { name: 'Heliosphere', kick: "The Sun's own bubble", facts: [['Made by', 'the solar wind — particles streaming from the Sun'], ['Termination shock', 'about 90 AU — the wind suddenly slows'], ['Heliopause', 'about 120 AU — beyond it, interstellar space'], ['Crossed by', 'Voyager 1 (2012), Voyager 2 (2018)']],
    note: 'The solar wind pushes back the gas between the stars and blows a vast bubble with every planet inside it. It holds off much of the harmful cosmic radiation. Its true shape is still debated — this is the comet-like model with a tail.' },
  oort: { name: 'Oort Cloud', kick: 'The far edge of the Solar System', facts: [['Distance', 'roughly 2,000 to 100,000 AU'], ['Outer edge', 'about 1.6 light-years away'], ['How many bodies', 'billions, perhaps trillions of icy ones'], ['Ever seen?', 'no — it is still inferred']],
    note: 'A thought-to-exist shell of icy bodies around the whole Solar System, where long-period comets are believed to come from. Voyager 1 will reach its inner edge in about 300 years and take some 30,000 years to cross it.' },
  ceres: { name: 'Ceres', kick: 'Dwarf planet · in the asteroid belt', facts: [['Diameter', 'about 940 km'], ['Distance from the Sun', 'about 414 million km (2.8 AU)'], ['One year', 'about 4.6 Earth years'], ['Moons', 'none']],
    note: 'The largest member of the asteroid belt and the only dwarf planet in the inner Solar System. Bright salt spots shine in Occator crater; the Dawn spacecraft orbited it from 2015.' },
  pluto: { name: 'Pluto', kick: 'Dwarf planet · in the Kuiper Belt', facts: [['Diameter', '2,377 km'], ['Average distance from the Sun', 'about 5.9 billion km (39.5 AU)'], ['One year', 'about 248 Earth years'], ['Moons', '5 — the largest is Charon']],
    note: 'A dwarf planet since 2006, with a vast heart-shaped plain of nitrogen ice. New Horizons flew past in 2015 and took the first close pictures; the southern part it could not see is filled in from the colours around it.' },
  eris: { name: 'Eris', kick: 'Dwarf planet · beyond the Kuiper Belt', facts: [['Diameter', '2,326 km'], ['Average distance from the Sun', 'about 10.2 billion km (68 AU)'], ['One year', 'about 559 Earth years'], ['Moons', '1 — Dysnomia']],
    note: 'Its discovery in 2005 is what made astronomers ask whether Pluto was still a planet. About 27% more massive than Pluto. No spacecraft has been there — this look is an artist’s impression.' },
  haumea: { name: 'Haumea', kick: 'Dwarf planet · in the Kuiper Belt', facts: [['Size', 'about 2,100 × 1,680 × 1,070 km'], ['Average distance from the Sun', 'about 6.4 billion km (43 AU)'], ['One year', 'about 284 Earth years'], ['Moons', '2']],
    note: 'It spins once in only 4 hours — so fast that it has stretched into an egg shape — and it has a thin ring. This look is an artist’s impression.' },
  makemake: { name: 'Makemake', kick: 'Dwarf planet · in the Kuiper Belt', facts: [['Diameter', 'about 1,430 km'], ['Average distance from the Sun', 'about 6.8 billion km (45.5 AU)'], ['One year', 'about 306 Earth years'], ['Moons', '1']],
    note: 'Found just after Easter 2005, so it was named after the creator god of Easter Island. Covered in reddish methane ice. This look is an artist’s impression.' },
  moon: { name: 'The Moon', facts: [['Diameter', '3,474 km'], ['Distance from Earth', '384,400 km'], ['One orbit', '27.3 days'], ['People who walked on it', '12, from 1969 to 1972']],
    note: "Earth's only natural satellite. It turns once in the time it takes to go round us, so we always see the same face." },
  phobos: { name: 'Phobos', facts: [['Diameter', 'about 22 km'], ['Distance from Mars', '9,376 km'], ['One orbit', '7 h 39 min'], ['Discovered', '1877 — Asaph Hall']],
    note: 'A small, lumpy moon skimming low over Mars and slowly spiralling in — in tens of millions of years it will break apart.' },
  deimos: { name: 'Deimos', facts: [['Diameter', 'about 12 km'], ['Distance from Mars', '23,463 km'], ['One orbit', '30 h 18 min'], ['Discovered', '1877 — Asaph Hall']],
    note: "The smaller and farther of Mars's two moons; dust smooths its surface. This look is painted." },
  io: { name: 'Io', facts: [['Diameter', '3,643 km'], ['Distance from Jupiter', '421,700 km'], ['One orbit', '1.77 days'], ['Discovered', '1610 — Galileo']],
    note: "The most volcanic place in the Solar System, with more than 400 active volcanoes; Jupiter's pull keeps kneading and heating its insides." },
  europa: { name: 'Europa', facts: [['Diameter', '3,122 km'], ['Distance from Jupiter', '671,100 km'], ['One orbit', '3.55 days'], ['Discovered', '1610 — Galileo']],
    note: 'Beneath its shell of ice lies a vast salty ocean — one of the most promising places to look for life.' },
  ganymede: { name: 'Ganymede', facts: [['Diameter', '5,268 km'], ['Distance from Jupiter', '1,070,400 km'], ['One orbit', '7.15 days'], ['Discovered', '1610 — Galileo']],
    note: 'The largest moon in the Solar System — bigger than Mercury — and the only moon with a magnetic field of its own.' },
  callisto: { name: 'Callisto', facts: [['Diameter', '4,821 km'], ['Distance from Jupiter', '1,882,700 km'], ['One orbit', '16.7 days'], ['Discovered', '1610 — Galileo']],
    note: 'The most heavily cratered surface in the Solar System, barely changed for billions of years. Gaps in the map are filled in.' },
  enceladus: { name: 'Enceladus', facts: [['Diameter', '504 km'], ['Distance from Saturn', '238,000 km'], ['One orbit', '1.37 days'], ['Discovered', '1789 — William Herschel']],
    note: 'Geysers of water ice shoot into space from cracks at its south pole — there is an ocean hidden underneath.' },
  rhea: { name: 'Rhea', facts: [['Diameter', '1,527 km'], ['Distance from Saturn', '527,100 km'], ['One orbit', '4.52 days'], ['Discovered', '1672 — Giovanni Cassini']],
    note: "Saturn's second-largest moon, almost all ice and covered in craters." },
  titan: { name: 'Titan', facts: [['Diameter', '5,150 km'], ['Distance from Saturn', '1,221,900 km'], ['One orbit', '15.95 days'], ['Discovered', '1655 — Christiaan Huygens']],
    note: 'An orange, hazy world under a thick nitrogen atmosphere, with lakes and rivers of methane. The Huygens probe landed here in 2005.' },
  iapetus: { name: 'Iapetus', facts: [['Diameter', '1,469 km'], ['Distance from Saturn', '3,560,800 km'], ['One orbit', '79.3 days'], ['Discovered', '1671 — Giovanni Cassini']],
    note: 'A two-tone moon: one side dark as coal, the other bright as snow, with a long mountain ridge along its equator.' },
  miranda: { name: 'Miranda', facts: [['Diameter', '472 km'], ['Distance from Uranus', '129,900 km'], ['One orbit', '1.41 days'], ['Discovered', '1948 — Gerard Kuiper']],
    note: 'A patchwork surface with Verona Rupes, a cliff some 20 km high. Voyager 2 saw only its southern side — the rest is filled in.' },
  ariel: { name: 'Ariel', facts: [['Diameter', '1,158 km'], ['Distance from Uranus', '190,900 km'], ['One orbit', '2.52 days'], ['Discovered', '1851 — William Lassell']],
    note: "The brightest and youngest surface among Uranus's moons. The northern part is filled in." },
  titania: { name: 'Titania', facts: [['Diameter', '1,577 km'], ['Distance from Uranus', '436,300 km'], ['One orbit', '8.71 days'], ['Discovered', '1787 — William Herschel']],
    note: "Uranus's largest moon, split by huge canyons. The northern part is filled in." },
  oberon: { name: 'Oberon', facts: [['Diameter', '1,523 km'], ['Distance from Uranus', '583,500 km'], ['One orbit', '13.46 days'], ['Discovered', '1787 — William Herschel']],
    note: "Uranus's outermost large moon, old and heavily cratered. The northern part is filled in." },
  triton: { name: 'Triton', facts: [['Diameter', '2,707 km'], ['Distance from Neptune', '354,800 km'], ['One orbit', '5.88 days — backwards'], ['Discovered', '1846 — William Lassell']],
    note: 'It orbits backwards — probably a Kuiper Belt object that Neptune captured. Nitrogen geysers erupt on a surface near −235 °C. The northern part is filled in.' },
  charon: { name: 'Charon', facts: [['Diameter', '1,212 km'], ['Distance from Pluto', '19,600 km'], ['One orbit', '6.39 days'], ['Discovered', '1978 — James Christy']],
    note: "Half Pluto's size; the two always keep the same faces turned to each other. The reddish polar cap is nicknamed Mordor. The southern part is filled in." },
  voyager1: { name: 'Voyager 1', kick: 'Spacecraft · in interstellar space', facts: [['Launched', '5 September 1977'], ['Distance from the Sun', 'about 170 AU (2026) — some 25 billion km'], ['Crossed the heliopause', 'August 2012, at 121.6 AU'], ['A signal takes', 'about 23 hours to arrive']],
    note: 'The farthest human-made object. It flew past Jupiter and Saturn and is now between the stars, carrying the Golden Record of Earth’s sounds and pictures. The craft is a symbol, not to scale.' },
  voyager2: { name: 'Voyager 2', kick: 'Spacecraft · in interstellar space', facts: [['Launched', '20 August 1977'], ['Distance from the Sun', 'about 145 AU (2026) — some 21.7 billion km'], ['Crossed the heliopause', 'November 2018, at about 119 AU'], ['Only one ever to', 'fly past Uranus and Neptune']],
    note: 'The only spacecraft to pass all four giant planets; nearly every close-up of Uranus (1986) and Neptune (1989) is its work. The craft is a symbol, not to scale.' },
};
Object.assign(EN_DATA, EN_X);
EXTRAS.forEach((d) => { d.facts = d.f; d.note = d.n; d.info = []; });
// regions take indices 10–12 so their chips follow the planets; the rest come after
EXTRAS.forEach((d) => BODIES.push(d));
const KEY_IDX = {};
BODIES.forEach((d, i) => { KEY_IDX[d.key] = i; });
ORDER.push(KEY_IDX.pluto, KUIPER, HELIO, OORT);
const kindOf = (i) => (i >= 0 && BODIES[i] ? BODIES[i].kind : '');
const parentIdx = (i) => KEY_IDX[BODIES[i].parent];
const moonsOf = (key) => EXTRAS.filter((d) => d.kind === 'moon' && d.parent === key).map((d) => KEY_IDX[d.key]);
const DWARFS = EXTRAS.filter((d) => d.kind === 'dwarf').map((d) => KEY_IDX[d.key]);
const CRAFT = EXTRAS.filter((d) => d.kind === 'craft').map((d) => KEY_IDX[d.key]);

/* painters for bodies without a usable photograph, and the placeholders before one arrives */
const rockyPainter = (tint, seed) => (x, y, z) => {
  const n = fbm(x * 3 + seed, y * 3, z * 3), c = Math.abs(noise(x * 10 + seed, y * 10, z * 10)), c2 = Math.abs(noise(x * 24, y * 24 + seed, z * 24));
  const v = 0.62 + n * 0.4 - c * 0.2;
  return { map: [tint[0] * v, tint[1] * v, tint[2] * v], bumpMap: 128 + n * 120 - (1 - c) * 50 - (1 - c2) * 30 };
};
const icyPainter = (tint, seed) => (x, y, z) => {
  const n = fbm(x * 2.5 + seed, y * 2.5, z * 2.5), l = Math.abs(noise(x * 14, y * 14 + seed, z * 14));
  const v = 0.8 + n * 0.28 - (l < 0.035 ? 0.16 : 0);
  return [tint[0] * v, tint[1] * v, tint[2] * v];
};
PAINTERS.titan = (x, y, z, lat) => { const n = fbm(x * 2, y * 6, z * 2); return mix3([190, 120, 48], [232, 172, 92], clamp(0.5 + Math.sin(lat * 5) * 0.12 + n * 0.45, 0, 1)); };
PAINTERS.ceres = (x, y, z, lat, lon) => {
  const o = rockyPainter([150, 146, 140], 3)(x, y, z);
  let dl = lon + 2.11; dl = Math.atan2(Math.sin(dl), Math.cos(dl));
  if ((dl / 0.05) ** 2 + ((lat - 0.35) / 0.035) ** 2 < 1) o.map = [245, 243, 236]; // the bright salts of Occator
  return o;
};
PAINTERS.haumea = (x, y, z, lat, lon) => {
  const c = icyPainter([238, 235, 230], 5)(x, y, z);
  let dl = lon - 0.8; dl = Math.atan2(Math.sin(dl), Math.cos(dl));
  return (dl / 0.45) ** 2 + (lat / 0.35) ** 2 < 1 ? mix3(c, [140, 70, 55], 0.6) : c; // its dark red spot
};
function painterFor(d, k) {
  if (d.paint === 'rocky') return rockyPainter(d.tint, k * 1.7);
  if (d.paint === 'icy') return icyPainter(d.tint, k * 1.7);
  return PAINTERS[d.paint];
}

/* ---------------- three.js scene ---------------- */
let renderer;
try {
  // mediump halves fragment cost on phones; the sun shader opts back into highp for its hash noise
  renderer = new THREE.WebGLRenderer({ antialias: !IS_MOBILE, precision: IS_MOBILE ? 'mediump' : 'highp', powerPreference: 'high-performance' });
} catch (e) {
  fail();
  return;
}
let dpr = Math.min(devicePixelRatio || 1, Q.dpr);
renderer.setPixelRatio(dpr);
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.1;
renderer.domElement.setAttribute('aria-hidden', 'true');
root.prepend(renderer.domElement);
const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(45, 1, 0.05, 30000);

// post-processing: bloom picks up only what is brighter than the threshold (the sun, its glow, bright rims)
const composer = new EffectComposer(renderer);
composer.addPass(new RenderPass(scene, camera));
const bloom = new UnrealBloomPass(new THREE.Vector2(Q.bloom, Q.bloom), 0.6, 0.5, 1.0);
composer.addPass(bloom);
composer.addPass(new OutputPass());

// lost GPU context (tab backgrounded on a phone, driver reset): keep the page alive and re-upload on restore
renderer.domElement.addEventListener('webglcontextlost', (e) => { e.preventDefault(); $('glmsg').hidden = false; });
renderer.domElement.addEventListener('webglcontextrestored', () => {
  scene.traverse((o) => {
    const mats = o.material ? [].concat(o.material) : [];
    for (const m of mats) {
      for (const k of ['map', 'bumpMap', 'roughnessMap', 'emissiveMap', 'alphaMap']) if (m[k]) m[k].needsUpdate = true;
      if (m.uniforms) for (const u of Object.values(m.uniforms)) if (u.value && u.value.isTexture) u.value.needsUpdate = true;
    }
  });
  $('glmsg').hidden = true; dirty = true;
});

scene.add(new THREE.AmbientLight(0xffffff, 0.07));
scene.add(new THREE.PointLight(0xfff1dd, 2.8, 0, 0));

// stars
{
  const n = Math.round(Q.stars * 0.45), pos = new Float32Array(n * 3), col = new Float32Array(n * 3);
  for (let i = 0; i < n; i++) {
    const u = rng() * 2 - 1, th = rng() * Math.PI * 2, s = Math.sqrt(1 - u * u), R = 9000 + rng() * 3000;
    pos.set([R * s * Math.cos(th), R * u, R * s * Math.sin(th)], i * 3);
    const tint = rng(), b = 0.3 + rng() * 0.5;
    const c = tint < 0.15 ? [1, 0.8, 0.6] : tint < 0.3 ? [0.7, 0.8, 1] : [1, 1, 1];
    col.set(c.map((v) => v * b), i * 3);
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setAttribute('color', new THREE.BufferAttribute(col, 3));
  scene.add(new THREE.Points(g, new THREE.PointsMaterial({ size: 1.7, sizeAttenuation: false, vertexColors: true, depthWrite: false })));
}
/* the Milky Way: Solar System Scope's star map, which is drawn in galactic coordinates, turned
   so it sits where it really is around the planets' plane. Drawn first, always behind everything. */
const SKY_TO_GAL = (() => {
  const e = 23.4393 * Math.PI / 180, c = Math.cos(e), sn = Math.sin(e);
  const toEcl = new THREE.Matrix3().set(1, 0, 0, 0, 0, -1, 0, 1, 0);              // scene → ecliptic
  const toEq = new THREE.Matrix3().set(1, 0, 0, 0, c, -sn, 0, sn, c);             // ecliptic → equatorial
  const toGal = new THREE.Matrix3().set(-0.0548755604, -0.8734370902, -0.4838350155, 0.4941094279, -0.44482963, 0.7469822445, -0.867666149, -0.1980763734, 0.4559837762);
  return toGal.multiply(toEq).multiply(toEcl);
})();
const skyMat = new THREE.ShaderMaterial({
  uniforms: { uSky: { value: null }, uToGal: { value: SKY_TO_GAL }, uBright: { value: 1.0 } },
  vertexShader: 'varying vec3 vDir; void main(){ vDir = position; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
  fragmentShader: `uniform sampler2D uSky; uniform mat3 uToGal; uniform float uBright; varying vec3 vDir;
    void main(){
      vec3 g = uToGal * normalize(vDir);
      vec2 uv = vec2(fract(0.5 - atan(g.y, g.x) / 6.2831853), 0.5 + asin(clamp(g.z, -1.0, 1.0)) / 3.1415927);
      gl_FragColor = vec4(texture2D(uSky, uv).rgb * uBright, 1.0);
    }`,
  side: THREE.BackSide, depthWrite: false, depthTest: false,
});
const skyMesh = new THREE.Mesh(new THREE.SphereGeometry(1000, 48, 24), skyMat);
skyMesh.renderOrder = -10; skyMesh.frustumCulled = false; skyMesh.visible = false;
scene.add(skyMesh);

// sun: boiling granulation, limb darkening, sunspots with their penumbrae and faculae.
// Everything is a function of uTime, so a paused tour frame is always the same frame.
const NOISE_GLSL = `
    float hash(vec3 p){ p = fract(p*0.3183099+.1); p *= 17.0; return fract(p.x*p.y*p.z*(p.x+p.y+p.z)); }
    float vnoise(vec3 x){ vec3 i=floor(x), f=fract(x); f=f*f*(3.-2.*f);
      return mix(mix(mix(hash(i),hash(i+vec3(1,0,0)),f.x), mix(hash(i+vec3(0,1,0)),hash(i+vec3(1,1,0)),f.x),f.y),
                 mix(mix(hash(i+vec3(0,0,1)),hash(i+vec3(1,0,1)),f.x), mix(hash(i+vec3(0,1,1)),hash(i+vec3(1,1,1)),f.x),f.y), f.z); }
    #ifdef SUN_LITE
      #define FBM_OCT 3
    #else
      #define FBM_OCT 5
    #endif
    float fbm(vec3 p){ float s=0., a=.5; for(int i=0;i<FBM_OCT;i++){ s+=a*vnoise(p); p*=2.03; a*=.5; } return s; }`;
const sunSpot = (lat, lon, r) => { const la = lat * Math.PI / 180, lo = lon * Math.PI / 180; return new THREE.Vector4(Math.cos(la) * Math.cos(lo), Math.sin(la), Math.cos(la) * Math.sin(lo), r); };
const sunMat = new THREE.ShaderMaterial({
  uniforms: {
    uTime: { value: 0 },
    // two active regions north and south of the equator, as on the real Sun, and a lone spot
    uSpots: { value: [sunSpot(14, 30, 0.07), sunSpot(17, 41, 0.034), sunSpot(-11, 150, 0.056), sunSpot(-14, 160, 0.026), sunSpot(21, 255, 0.042)] },
  },
  defines: IS_MOBILE ? { SUN_LITE: 1 } : {},
  extensions: { derivatives: true },
  vertexShader: `varying vec3 vP; varying vec3 vN; varying vec3 vV;
    void main(){ vP = position; vec4 wp = modelMatrix*vec4(position,1.); vN = normalize(mat3(modelMatrix)*normal); vV = normalize(cameraPosition-wp.xyz); gl_Position = projectionMatrix*viewMatrix*wp; }`,
  fragmentShader: `precision highp float;
    uniform float uTime; uniform vec4 uSpots[5]; varying vec3 vP; varying vec3 vN; varying vec3 vV;
    ${NOISE_GLSL}
    vec3 hash3(vec3 p){ return fract(sin(vec3(dot(p,vec3(127.1,311.7,74.7)), dot(p,vec3(269.5,183.3,246.1)), dot(p,vec3(113.5,271.9,124.6))))*43758.5453); }
    #ifdef SUN_LITE
      #define C0 0
      #define CELL_OFF 0.5
      #define JIT 0.3
    #else
      #define C0 -1
      #define CELL_OFF 0.0
      #define JIT 0.38
    #endif
    // granulation: distance to the nearest and second-nearest cell centre; the centres wander, so the cells boil
    vec2 cells(vec3 p, float t){
      vec3 i = floor(p - CELL_OFF), f = p - i; float d1 = 9., d2 = 9.;
      for (int z = C0; z <= 1; z++) for (int y = C0; y <= 1; y++) for (int x = C0; x <= 1; x++) {
        vec3 g = vec3(float(x), float(y), float(z)), h = hash3(i + g);
        vec3 r = g + 0.5 + JIT * sin(t * (0.6 + 0.6 * h.zxy) + 6.2831 * h) - f;
        float d = dot(r, r);
        if (d < d1) { d2 = d1; d1 = d; } else if (d < d2) { d2 = d; }
      }
      return sqrt(vec2(d1, d2));
    }
    // x: umbra, y: penumbra with its radial filaments, z: faculae around the group
    vec3 spots(vec3 n){
      float um = 0., pen = 0., fac = 0.;
      for (int k = 0; k < 5; k++) {
        vec3 c = normalize(uSpots[k].xyz), dl = n - c;
        float d = length(dl) / uSpots[k].w + 0.2 * (vnoise(n * 48.0 + float(k) * 7.0) - 0.5);
        vec3 t1 = normalize(cross(c, vec3(0.0, 1.0, 0.001))), t2 = cross(c, t1);
        float fil = 0.5 + 0.5 * sin(atan(dot(dl, t2), dot(dl, t1)) * 44.0 + vnoise(n * 90.0) * 5.0);
        pen = max(pen, (1.0 - smoothstep(0.82, 1.0, d)) * (0.75 + 0.25 * fil));
        um = max(um, 1.0 - smoothstep(0.34, 0.46, d));
        fac = max(fac, smoothstep(0.9, 1.15, d) * (1.0 - smoothstep(1.3, 3.4, d)));
      }
      return vec3(um, pen, fac);
    }
    void main(){
      vec3 n = normalize(vP);
      vec3 gp = n * 52.0;
      vec2 w = cells(gp + vec3(0.0, uTime * 0.01, 0.0), uTime * 0.35);
      float lanes = smoothstep(0.0, 0.3, w.y - w.x);
      float gran = (0.66 + 0.34 * lanes) * (0.88 + 0.2 * (1.0 - w.x));
      // where a cell is smaller than a pixel, fade to its average so the disc never shimmers
      gran = mix(gran, 0.9, smoothstep(0.3, 0.85, length(fwidth(gp))));
      float mott = fbm(n * 5.0 + vec3(uTime * 0.02, 0.0, -uTime * 0.015));
      float I = gran * (0.84 + 0.3 * mott);
      float mu = clamp(dot(vN, vV), 0.0, 1.0), m1 = 1.0 - mu;
      vec3 sp = spots(n);
      I *= 1.0 + sp.z * 0.45 * m1;                 // faculae show up towards the limb
      I *= mix(1.0, 0.5, sp.y) * mix(1.0, 0.32, sp.x);
      float limb = 1.0 - 0.56 * m1 - 0.2 * m1 * m1; // limb darkening, quadratic law
      vec3 hot = pow(vec3(1.0, 0.8, 0.44), vec3(2.2)), warm = pow(vec3(0.96, 0.42, 0.1), vec3(2.2));
      vec3 col = mix(warm, hot, pow(mu, 0.45)) * I * limb;
      col *= mix(vec3(1.0), vec3(1.0, 0.6, 0.4), sp.x * 0.7);
      gl_FragColor = vec4(col * 1.4, 1.);          // linear HDR: only the brightest cells cross the bloom threshold
    }`,
});
const sun = new THREE.Mesh(new THREE.SphereGeometry(SUN_R, Q.sunSeg[0], Q.sunSeg[1]), sunMat);
scene.add(sun);
// round the limb: the thin red chromosphere, prominences rising and changing, and a faint corona.
// A sheet through the Sun's centre that turns to face the camera; the disc itself hides its middle.
const rimMat = new THREE.ShaderMaterial({
  uniforms: { uTime: sunMat.uniforms.uTime, uLimb: { value: 1 } },
  defines: IS_MOBILE ? { SUN_LITE: 1 } : {},
  vertexShader: 'varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
  fragmentShader: `precision highp float;
    uniform float uTime; uniform float uLimb; varying vec2 vUv;
    ${NOISE_GLSL}
    void main(){
      vec2 q = (vUv - 0.5) * 4.4;
      float r = length(q) * uLimb;               // 1.0 exactly on the limb the camera sees
      if (r < 0.99) discard;
      float h = r - 1.0;
      vec2 dir = q / max(length(q), 1e-4);
      float chromo = exp(-h * 70.0);
      float where = smoothstep(0.58, 0.88, vnoise(vec3(dir * 2.2, uTime * 0.015)));
      float ridge = 1.0 - abs(2.0 * fbm(vec3(dir * 9.0, h * 10.0 - uTime * 0.12)) - 1.0);   // thin, twisting strands
      float prom = where * smoothstep(0.7, 0.92, ridge + 0.3 * exp(-h * 10.0) - h * 1.2) * exp(-h * 6.0);
      float corona = pow(1.0 / r, 7.0) * 0.12 * (0.6 + 0.4 * vnoise(vec3(dir * 3.5, uTime * 0.01)));
      vec3 col = vec3(1.0, 0.13, 0.04) * (chromo * 0.7 + prom * 1.25) + vec3(1.0, 0.86, 0.72) * corona;
      // fade well before the sheet's own edge, however close the camera is
      gl_FragColor = vec4(col * (1.0 - smoothstep(1.75, 2.15, length(q))), 1.0);
    }`,
  transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide,
});
const sunRim = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), rimMat);
sunRim.scale.setScalar(SUN_R * 4.4);
scene.add(sunRim);
function glowTex(stops) {
  const cv = document.createElement('canvas'); cv.width = cv.height = 256; const g = cv.getContext('2d');
  const gr = g.createRadialGradient(128, 128, 0, 128, 128, 128); stops.forEach(([o, c]) => gr.addColorStop(o, c));
  g.fillStyle = gr; g.fillRect(0, 0, 256, 256); const t = new THREE.CanvasTexture(cv); t.colorSpace = THREE.SRGBColorSpace; return t;
}
const glow1 = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex([[0, 'rgba(255,230,170,1)'], [0.22, 'rgba(255,190,90,0.85)'], [0.45, 'rgba(255,120,30,0.25)'], [1, 'rgba(255,90,0,0)']]), blending: THREE.AdditiveBlending, depthWrite: false, transparent: true, opacity: 0.38 }));
glow1.scale.setScalar(SUN_R * 4.2); scene.add(glow1);
const glow2 = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex([[0, 'rgba(255,200,120,0.5)'], [0.3, 'rgba(255,140,50,0.12)'], [1, 'rgba(255,100,0,0)']]), blending: THREE.AdditiveBlending, depthWrite: false, transparent: true, opacity: 0.3 }));
glow2.scale.setScalar(SUN_R * 12); scene.add(glow2);

// atmosphere rim shader
function atmosphere(radius, color, strength = 1, sunset = false) {
  const m = new THREE.ShaderMaterial({
    uniforms: { uColor: { value: new THREE.Color(...color) }, uS: { value: strength } },
    defines: sunset ? { SUNSET: 1 } : {},
    vertexShader: `varying vec3 vN; varying vec3 vV; varying vec3 vW;
      void main(){ vec4 wp = modelMatrix*vec4(position,1.); vW = wp.xyz; vN = normalize(mat3(modelMatrix)*normal); vV = normalize(cameraPosition-wp.xyz); gl_Position = projectionMatrix*viewMatrix*wp; }`,
    fragmentShader: `uniform vec3 uColor; uniform float uS; varying vec3 vN; varying vec3 vV; varying vec3 vW;
      void main(){ float rim = pow(1.0 - max(dot(vN, vV), 0.), 4.0);
        float ndl = dot(vN, normalize(-vW));
        float lit = clamp(ndl*0.8+0.35, 0., 1.);
        vec3 col = uColor;
        #ifdef SUNSET
          // where day turns to night the light has crossed the most air: it reddens, then fades
          col = mix(uColor, vec3(1.0, 0.42, 0.16), smoothstep(-0.25, 0.05, ndl) * (1.0 - smoothstep(0.05, 0.4, ndl)));
          lit = smoothstep(-0.3, 0.25, ndl);
        #endif
        gl_FragColor = vec4(col*rim*lit*uS*0.6, 1.); }`,
    blending: THREE.AdditiveBlending, transparent: true, depthWrite: false,
  });
  return new THREE.Mesh(new THREE.SphereGeometry(radius, Q.atmoSeg[0], Q.atmoSeg[1]), m);
}

// orbits
const orbitMat = new THREE.LineBasicMaterial({ color: 0x8fa3d6, transparent: true, opacity: 0.22, depthWrite: false });
for (const p of PLANETS) {
  const pts = []; for (let k = 0; k <= 256; k++) { const a = k / 256 * Math.PI * 2; pts.push(new THREE.Vector3(Math.cos(a) * p.a, 0, Math.sin(a) * p.a)); }
  scene.add(new THREE.LineLoop(new THREE.BufferGeometry().setFromPoints(pts), orbitMat));
}

// asteroid belt
const belt = new THREE.InstancedMesh(new THREE.IcosahedronGeometry(0.11, 0), new THREE.MeshStandardMaterial({ color: 0x8a7f72, roughness: 1, flatShading: true }), Q.belt);
{
  const m = new THREE.Matrix4(), q = new THREE.Quaternion(), s = new THREE.Vector3(), v = new THREE.Vector3(), e = new THREE.Euler();
  for (let i = 0; i < belt.count; i++) {
    const a = rng() * Math.PI * 2, r = 37 + (rng() + rng() - 1) * 3.2;
    v.set(Math.cos(a) * r, (rng() - 0.5) * 1.2, Math.sin(a) * r);
    e.set(rng() * 6, rng() * 6, rng() * 6); q.setFromEuler(e); s.setScalar(0.4 + rng() * 1.4);
    belt.setMatrixAt(i, m.compose(v, q, s));
  }
}
scene.add(belt);

/* Saturn's ring geometry and the texture the ring and its shadow share */
const ringC = new THREE.Vector3(), ringN = new THREE.Vector3();
// one texture serves the rings and the shadow they throw, so the photograph replaces both at once
const ringTexU = { value: null }, ringTint = new THREE.Color(0xd8d0c0);
// Earth's clouds throw shadows on the ground: the cloud photograph, and how far the cloud layer has turned
const cloudTexU = { value: null }, cloudShiftU = { value: 0 };
// moon shadows: up to four moons per planet, centre and radius, refreshed every frame
const SHADOW_MOONS = 4, moonShadows = {};
/* What a planet's surface shader adds to the standard one:
   night: city lights on the night side only; ring: Saturn's ring shadow;
   moons: eclipses where a moon stands between the Sun and the ground; clouds: cloud shadows. */
function planetShader(mat, f) {
  mat.customProgramCacheKey = () => 'planet:' + (f.night ? 'n' : '') + (f.ring ? 'r' : '') + (f.moons ? 'm' : '') + (f.clouds ? 'c' : '');
  mat.onBeforeCompile = (sh) => {
    const U = sh.uniforms;
    if (f.ring) Object.assign(U, { uRingC: { value: ringC }, uRingN: { value: ringN }, uRingTex: ringTexU, uRingIn: { value: f.ring[0] }, uRingOut: { value: f.ring[1] } });
    if (f.moons) U.uMoons = { value: f.moons };
    if (f.clouds) Object.assign(U, { uClouds: cloudTexU, uCloudShift: cloudShiftU });
    sh.vertexShader = sh.vertexShader.replace('#include <common>', '#include <common>\nvarying vec3 vWP;')
      .replace('#include <worldpos_vertex>', '#include <worldpos_vertex>\nvWP = (modelMatrix * vec4(transformed, 1.0)).xyz;');
    let decl = 'varying vec3 vWP;\n';
    if (f.ring) decl += 'uniform vec3 uRingC; uniform vec3 uRingN; uniform sampler2D uRingTex; uniform float uRingIn; uniform float uRingOut;\n';
    if (f.moons) decl += `uniform vec4 uMoons[${SHADOW_MOONS}];\n`;
    if (f.clouds) decl += 'uniform sampler2D uClouds; uniform float uCloudShift;\n';
    let fs = sh.fragmentShader.replace('#include <common>', '#include <common>\n' + decl);
    if (f.clouds) fs = fs.replace('#include <map_fragment>', `#include <map_fragment>
      diffuseColor.rgb *= 1.0 - 0.4 * texture2D(uClouds, vec2(fract(vMapUv.x + uCloudShift), vMapUv.y)).g;`);
    if (f.night) fs = fs.replace('#include <emissivemap_fragment>', `#include <emissivemap_fragment>
      vec3 sunView = (viewMatrix * vec4(0., 0., 0., 1.)).xyz;
      totalEmissiveRadiance *= smoothstep(0.1, -0.25, dot(normal, normalize(sunView + vViewPosition)));`);
    let shade = '';
    if (f.ring) shade += `{
        vec3 L = normalize(-vWP); float dn = dot(L, uRingN);
        float s = dot(uRingC - vWP, uRingN) / (abs(dn) < 1e-4 ? 1e-4 : dn);
        float r = length(vWP + L * s - uRingC);
        float u = (r - uRingIn) / (uRingOut - uRingIn);
        float a = texture2D(uRingTex, vec2(clamp(u, 0., 1.), .5)).a * step(0., s) * step(0., u) * step(u, 1.);
        outgoingLight *= 1. - a * 0.8;
      }`;
    if (f.moons) shade += `{
        vec3 L = normalize(-vWP); float lit = 1.0;
        for (int k = 0; k < ${SHADOW_MOONS}; k++) {
          vec4 m = uMoons[k];
          if (m.w <= 0.0) continue;
          vec3 cp = m.xyz - vWP; float t = dot(cp, L);
          if (t <= 0.0) continue;
          float d = length(cp - L * t);
          lit *= mix(0.1, 1.0, smoothstep(m.w * 0.7, m.w * 1.3, d));
        }
        outgoingLight *= lit;
      }`;
    if (shade) fs = fs.replace('#include <opaque_fragment>', shade + '\n      #include <opaque_fragment>');
    sh.fragmentShader = fs;
  };
}
/* the moons whose shadows each planet can catch */
function shadowSet(key) {
  const ids = key === 'earth' ? [KEY_IDX.moon] : moonsOf(key).slice(0, SHADOW_MOONS);
  if (!ids.length) return null;
  moonShadows[key] = { ids, list: Array.from({ length: SHADOW_MOONS }, () => new THREE.Vector4(0, 0, 0, 0)) };
  return moonShadows[key].list;
}
const _ms = new THREE.Vector3();
function updateMoonShadows() {
  for (const key in moonShadows) {
    const m = moonShadows[key];
    m.ids.forEach((id, j) => {
      if (!XR[id]) return;
      bodyWorld(id, _ms);
      m.list[j].set(_ms.x, _ms.y, _ms.z, BODIES[id].r);
    });
  }
}

/* Saturn's rings: planet shadow across the rings, and dimmer when seen from the unlit face */
function ringMaterial(radius) {
  return new THREE.ShaderMaterial({
    uniforms: { uTex: ringTexU, uC: { value: ringC }, uN: { value: ringN }, uR: { value: radius }, uTint: { value: ringTint } },
    vertexShader: `varying vec2 vUv; varying vec3 vWP;
      void main(){ vUv = uv; vec4 wp = modelMatrix*vec4(position,1.); vWP = wp.xyz; gl_Position = projectionMatrix*viewMatrix*wp; }`,
    fragmentShader: `uniform sampler2D uTex; uniform vec3 uC; uniform vec3 uN; uniform float uR; uniform vec3 uTint; varying vec2 vUv; varying vec3 vWP;
      void main(){
        vec4 tx = texture2D(uTex, vUv);
        vec3 L = normalize(-vWP), V = normalize(cameraPosition - vWP);
        float face = dot(uN, L) * dot(uN, V) >= 0. ? 1.0 : 0.45;
        vec3 oc = vWP - uC; float b = dot(oc, L); float h = b*b - (dot(oc, oc) - uR*uR);
        float shade = 1.0 - step(b, 0.) * smoothstep(0., uR*uR*0.06, h) * 0.9;
        gl_FragColor = vec4(tx.rgb * uTint * face * shade, tx.a);
      }`,
    transparent: true, side: THREE.DoubleSide, depthWrite: false,
  });
}

/* Real maps: Solar System Scope (CC BY 4.0, from their Wikimedia Commons copies), resized
   and converted to WebP — 2k on a computer, 1k on a phone. Each planet is painted in code at
   low resolution first; its photograph replaces the painting as soon as it arrives, and a body
   whose photograph cannot be fetched is painted again at full size instead. */
const TEX_DIR = new URL('../img/solar/' + (IS_MOBILE ? '1k' : '2k') + '/', import.meta.url).href;
const TEX_V = '1';
const LOW = IS_MOBILE ? 192 : 384;
const REAL = {
  mercury: { map: 'mercury', normal: 'mercury-normal' }, venus: { map: 'venus' },
  earth: { map: 'earth-day', night: 'earth-night', clouds: 'earth-clouds', rough: 'earth-rough', ...(IS_MOBILE ? {} : { normal: 'earth-normal' }) },
  moon: { map: 'moon', normal: 'moon-normal' }, mars: { map: 'mars' }, jupiter: { map: 'jupiter' },
  saturn: { map: 'saturn', ring: 'saturn-ring' }, uranus: { map: 'uranus' }, neptune: { map: 'neptune' },
};
const SRGB_KEYS = new Set(['map', 'night', 'ring']);
// Mars has no height map here: its photograph's own light and shade stands in for relief.
// The Moon and Mercury get normal maps made from real height maps instead.
const BUMP = { mars: 0.8 };
let texTotal = 0, texDone = 0;
function fetchTex(name, srgb, opt = {}) {
  texTotal++;
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.decoding = 'async';
    const done = () => {
      const t = new THREE.Texture(img);
      t.colorSpace = srgb ? THREE.SRGBColorSpace : THREE.NoColorSpace;
      t.anisotropy = Math.min(IS_MOBILE ? 4 : 8, renderer.capabilities.getMaxAnisotropy());
      if (opt.noMips) { t.generateMipmaps = false; t.minFilter = THREE.LinearFilter; }
      t.needsUpdate = true;
      renderer.initTexture(t);
      texDone++;
      resolve(t);
    };
    const fail = () => {
      if (img.complete && img.naturalWidth) { done(); return; }
      texDone++;
      reject(new Error('texture ' + name));
    };
    img.src = (opt.dir || TEX_DIR) + name + '.webp?v=' + TEX_V;
    if (img.decode) img.decode().then(done, fail); else { img.onload = done; img.onerror = fail; }
  });
}
function fetchBody(key) {
  const spec = REAL[key], names = Object.keys(spec);
  return Promise.all(names.map((k) => fetchTex(spec[k], SRGB_KEYS.has(k)))).then((list) => {
    const out = {};
    names.forEach((k, i) => { out[k] = list[i]; });
    return out;
  });
}
/* put new maps on a material; a map added or taken away changes its shader */
function swapMaps(mat, maps) {
  const keep = Object.values(maps);
  let rebuild = false;
  for (const k in maps) {
    const old = mat[k];
    if (old === maps[k]) continue;
    if (!old !== !maps[k]) rebuild = true;
    if (old && !keep.includes(old)) old.dispose();
    mat[k] = maps[k];
  }
  if (rebuild) mat.needsUpdate = true;
  dirty = true;
}

const bodies = [];
let moon, earthClouds, saturnRing;
function buildPlanet(p) {
  const orbit = new THREE.Group(); // positioned on orbit
  const tilt = new THREE.Group(); tilt.rotation.z = p.tilt; orbit.add(tilt);
  const mat = new THREE.MeshStandardMaterial({ roughness: 1, metalness: 0 });
  if (p.key === 'earth') { mat.emissive.set(0xffffff); mat.emissiveIntensity = 1.4; }
  const shadows = shadowSet(p.key);
  if (p.key === 'earth') planetShader(mat, { night: true, clouds: true, moons: shadows });
  else if (!p.ring && shadows) planetShader(mat, { moons: shadows });
  const mesh = new THREE.Mesh(new THREE.SphereGeometry(p.r, Q.seg[0], Q.seg[1]), mat);
  tilt.add(mesh);
  if (p.atmo) orbit.add(atmosphere(p.r * 1.05, p.atmo, p.key === 'earth' ? 1.5 : 0.9, p.key === 'earth'));
  if (p.key === 'earth') {
    earthClouds = new THREE.Mesh(new THREE.SphereGeometry(p.r * 1.012, Q.seg[0], Q.seg[1]), new THREE.MeshStandardMaterial({ transparent: true, depthWrite: false, roughness: 1 }));
    tilt.add(earthClouds);
    moon = new THREE.Mesh(new THREE.SphereGeometry(0.27, 48, 32), new THREE.MeshStandardMaterial({ roughness: 1 }));
    orbit.add(moon);
  }
  if (p.ring) {
    // the photograph of the rings runs from 1.18 to 2.32 Saturn radii
    const inner = p.r * 1.18, outer = p.r * 2.32, RW = Q.ring;
    const geo = new THREE.RingGeometry(inner, outer, 160, 1);
    const pos = geo.attributes.position, uv = geo.attributes.uv, v = new THREE.Vector3();
    for (let i = 0; i < pos.count; i++) { v.fromBufferAttribute(pos, i); uv.setXY(i, (v.length() - inner) / (outer - inner), 0.5); }
    const cv = document.createElement('canvas'); cv.width = RW; cv.height = 4; const g = cv.getContext('2d');
    for (let x = 0; x < RW; x++) {
      const u = x / RW;
      let a = 0.55 + 0.25 * Math.sin(u * 90) * Math.sin(u * 23) + 0.2 * noise(u * 40, 0.5, 0.5);
      if (u > 0.66 && u < 0.71) a *= 0.08; // Cassini division
      if (u < 0.12) a *= u / 0.12 * 0.6;
      a *= smooth(1.0, 0.92, u);
      const b = 200 + 40 * Math.sin(u * 37);
      g.fillStyle = `rgba(${b | 0},${(b * 0.88) | 0},${(b * 0.7) | 0},${clamp(a, 0, 1)})`; g.fillRect(x, 0, 1, 4);
    }
    ringTexU.value = new THREE.CanvasTexture(cv); ringTexU.value.colorSpace = THREE.SRGBColorSpace;
    const ring = new THREE.Mesh(geo, ringMaterial(p.r));
    ring.rotation.x = -Math.PI / 2; tilt.add(ring);
    planetShader(mat, { ring: [inner, outer], moons: shadows });
    ringN.set(-Math.sin(p.tilt), Math.cos(p.tilt), 0); // ring plane normal: +Y rotated by the axial tilt
    saturnRing = orbit;
  }
  scene.add(orbit);
  const b = { p, orbit, mesh };
  bodies.push(b);
  paintBody(b, LOW);
  if (p.key === 'earth') paintMoon(LOW / 2);
}
/* the painting in code: low resolution while the photographs load, full size if one fails */
function paintBody(b, size) {
  const p = b.p, maps = paintMaps(size, size / 2, PAINTERS[p.key]);
  swapMaps(b.mesh.material, { map: maps.map, bumpMap: maps.bumpMap || null, roughnessMap: maps.roughnessMap || null, emissiveMap: maps.emissiveMap || null });
  b.mesh.material.bumpScale = p.key === 'earth' ? 1.2 : 2.5;
  if (p.key === 'earth') {
    swapMaps(earthClouds.material, { map: paint(size, size / 2, PAINTERS.clouds), alphaMap: null });
    cloudTexU.value = null;
    earthClouds.material.opacity = 1;
  }
}
function paintMoon(size) {
  const mm = paintMaps(size, size / 2, PAINTERS.moon);
  swapMaps(moon.material, { map: mm.map, bumpMap: mm.bumpMap });
  moon.material.bumpScale = 2.5;
}
function applyReal(b, tx) {
  const key = b.p.key, m = b.mesh.material;
  if (key === 'earth') {
    swapMaps(m, { map: tx.map, bumpMap: null, roughnessMap: tx.rough, emissiveMap: tx.night, normalMap: tx.normal || null });
    m.emissiveIntensity = 1.6;
    cloudTexU.value = tx.clouds;
    // the cloud photograph is grey: white cloud, its brightness as opacity
    swapMaps(earthClouds.material, { map: null, alphaMap: tx.clouds });
    earthClouds.material.opacity = 0.92;
    return;
  }
  swapMaps(m, { map: tx.map, bumpMap: BUMP[key] ? tx.map : null, roughnessMap: null, emissiveMap: null, normalMap: tx.normal || null });
  if (BUMP[key]) m.bumpScale = BUMP[key];
  if (tx.normal) m.normalScale.set(1.4, 1.4);
  if (key === 'saturn') {
    ringTexU.value.dispose();
    ringTexU.value = tx.ring;
    ringTint.set(0xffffff);
  }
}
function applyMoon(tx) {
  swapMaps(moon.material, { map: tx.map, bumpMap: null, normalMap: tx.normal });
  moon.material.normalScale.set(1.4, 1.4);
}
/* 4k close-ups, on a computer only: fetched the first time the camera comes right up to a body */
const HI_DIR = new URL('../img/solar/4k/', import.meta.url).href;
const HI = {
  earth: { map: 'earth-day', night: 'earth-night', normal: 'earth-normal' }, moon: { map: 'moon' }, mercury: { map: 'mercury' },
  venus: { map: 'venus' }, mars: { map: 'mars' }, jupiter: { map: 'jupiter' }, saturn: { map: 'saturn' },
};
const HI_OK = !IS_MOBILE && renderer.capabilities.maxTextureSize >= 4096;
const hiState = {}, _hw = new THREE.Vector3();
function checkHi() {
  if (!HI_OK || focus < 0 || fly) return;
  const d = BODIES[focus], key = d.key;
  if (!HI[key] || hiState[key]) return;
  // close means about the distance a flight to it ends at, or nearer
  const near = d.kind === 'planet' ? viewDist(d) * fit() * 1.25 : Math.max(d.r * 5.5 * fit() * 1.25, d.r * 8);
  if (camera.position.distanceTo(bodyWorld(focus, _hw)) > near) return;
  hiState[key] = 'loading';
  const names = Object.keys(HI[key]);
  Promise.all(names.map((k) => fetchTex(HI[key][k], k !== 'normal', { dir: HI_DIR }))).then((list) => {
    const tx = {};
    names.forEach((k, i) => { tx[k] = list[i]; });
    if (key === 'moon') swapMaps(moon.material, { map: tx.map });
    else {
      const m = bodies[planetIdx(key) - 1].mesh.material;
      if (key === 'earth') swapMaps(m, { map: tx.map, emissiveMap: tx.night, normalMap: tx.normal });
      else swapMaps(m, { map: tx.map, bumpMap: BUMP[key] ? tx.map : m.bumpMap });
    }
    hiState[key] = 'done';
  }, () => { hiState[key] = 'failed'; });
}
/* Real time. Explore mode shows the Solar System as it is at this moment by the viewer's own
   clock: the planets at today's positions (mean orbital elements, J2000, good to about a degree),
   Earth turned by Greenwich sidereal time with its axis tilted the way it really is, and the Moon
   where it really is, so its phase is right. Night falls on Dhaka when it is night in Dhaka. */
const J2000 = Date.UTC(2000, 0, 1, 12, 0, 0);
const daysNow = () => (Date.now() - J2000) / 864e5;
// mean longitude L0 and its daily rate, longitude of perihelion, eccentricity (degrees)
const ELEMENTS = [[252.2503, 4.0923344, 77.4565, 0.2056], [181.9798, 1.6021302, 131.5637, 0.0068], [100.4664, 0.9856474, 102.9373, 0.0167], [355.4533, 0.5240208, 336.0602, 0.0934],
  [34.3965, 0.0830853, 14.7285, 0.0484], [49.9543, 0.0334442, 92.5988, 0.0539], [313.2381, 0.0117331, 170.9543, 0.0473], [304.8800, 0.0059810, 44.9648, 0.0086]];
const SPIN_DAYS = [58.646, -243.02, 0.99727, 1.026, 0.4135, 0.4440, -0.7183, 0.6713];
function realLon(i, d) {
  const [L0, n, peri, e] = ELEMENTS[i];
  const L = (L0 + n * d) * Math.PI / 180, M = L - peri * Math.PI / 180;
  return L + 2 * e * Math.sin(M) + 1.25 * e * e * Math.sin(2 * M); // equation of centre
}
const EPS = 23.4393 * Math.PI / 180;
const EQ_X = new THREE.Vector3(1, 0, 0), EQ_Y = new THREE.Vector3(0, -Math.sin(EPS), -Math.cos(EPS)), EQ_Z = new THREE.Vector3(0, Math.cos(EPS), -Math.sin(EPS));
const earthQ = new THREE.Quaternion(), _bm = new THREE.Matrix4(), _c0 = new THREE.Vector3(), _c2 = new THREE.Vector3();
/* Earth's orientation: the texture's Greenwich meridian faces the sidereal angle of Greenwich */
function earthOrientation(d, out) {
  const th = (280.46061837 + 360.98564736629 * d) * Math.PI / 180;
  _c0.copy(EQ_X).multiplyScalar(Math.cos(th)).addScaledVector(EQ_Y, Math.sin(th));
  _c2.copy(EQ_X).multiplyScalar(Math.sin(th)).addScaledVector(EQ_Y, -Math.cos(th));
  return out.setFromRotationMatrix(_bm.makeBasis(_c0, EQ_Z, _c2));
}
function moonLon(d) {
  const M = (134.963 + 13.064993 * d) * Math.PI / 180;
  return (218.316 + 13.176396 * d) * Math.PI / 180 + 6.289 * Math.PI / 180 * Math.sin(M);
}
let fast = true;                  // true: the planets sped up (the default); false: real time
// the sped-up motion starts from where the planets really are today
const liveA0 = PLANETS.map((p, i) => realLon(i, daysNow()));
const _ry = new THREE.Quaternion();
const planetPos = (i, t, out = new THREE.Vector3()) => { const p = PLANETS[i], a = p.a0 + p.w * t; return out.set(Math.cos(a) * p.a, 0, -Math.sin(a) * p.a); };


/* the objects themselves; XR[index] holds what each body needs at run time */
const XR = {};
const MOON_SEG = IS_MOBILE ? [24, 16] : [40, 28];
let kuiperPts, oortPts, helioShells = [];
const _w = new THREE.Vector3(), _q = new THREE.Quaternion();
function bodyWorld(i, out) {
  if (i === 0) return out.set(0, 0, 0);
  if (isPlanet(i)) return out.copy(bodies[i - 1].orbit.position);
  if (i === BELT) return out.copy(beltSpot);
  const x = XR[i];
  if (!x) return out.set(0, 0, 0);
  if (x.center) return out.copy(x.center);
  x.obj.updateWorldMatrix(true, false);
  return x.obj.getWorldPosition(out);
}
function planetIdx(key) { return PLANETS.findIndex((p) => p.key === key) + 1; }

function dwarfPos(o, t, out) {
  const nu = o.a0 + 0.32 / Math.sqrt(o.T) * t;
  const rau = o.a * (1 - o.e * o.e) / (1 + o.e * Math.cos(nu));
  const th = nu + o.peri * Math.PI / 180, Om = o.node * Math.PI / 180, inc = o.i * Math.PI / 180;
  const xe = Math.cos(Om) * Math.cos(th) - Math.sin(Om) * Math.sin(th) * Math.cos(inc);
  const ye = Math.sin(Om) * Math.cos(th) + Math.cos(Om) * Math.sin(th) * Math.cos(inc);
  const ze = Math.sin(th) * Math.sin(inc);
  return out.set(xe, ze, -ye).multiplyScalar(auToScene(rau));
}
function moonMaterial(d, k) {
  const m = new THREE.MeshStandardMaterial({ roughness: 1, metalness: 0 });
  const maps = paintMaps(IS_MOBILE ? 96 : 128, IS_MOBILE ? 48 : 64, painterFor(d, k));
  m.map = maps.map;
  if (maps.bumpMap) { m.bumpMap = maps.bumpMap; m.bumpScale = 1.4; }
  return m;
}
function buildExtras() {
  EXTRAS.forEach((d, k) => {
    const i = KEY_IDX[d.key];
    if (d.kind === 'moon') {
      if (d.existing) { XR[i] = { obj: moon, r: d.r }; return; }
      const parent = d.parent === 'pluto' ? XR[KEY_IDX.pluto].obj : bodies[planetIdx(d.parent) - 1].mesh.parent;
      const pivot = new THREE.Group(); pivot.rotation.x = (d.inc || 0) * Math.PI / 180; parent.add(pivot);
      const mesh = new THREE.Mesh(new THREE.SphereGeometry(d.r, MOON_SEG[0], MOON_SEG[1]), moonMaterial(d, k));
      pivot.add(mesh);
      if (d.atmo) mesh.add(atmosphere(d.r * 1.08, d.atmo, 1.3));
      const w = Math.sign(d.P) * Math.min(2.2, 2.6 / Math.sqrt(Math.abs(d.P)));
      XR[i] = { obj: mesh, r: d.r, w, a0: d.a0, R: d.orbit };
    } else if (d.kind === 'dwarf') {
      const g = new THREE.Group(); scene.add(g);
      const mesh = new THREE.Mesh(new THREE.SphereGeometry(d.r, MOON_SEG[0], MOON_SEG[1]), moonMaterial(d, k));
      if (d.shape) mesh.scale.set(d.shape[0], d.shape[2], d.shape[1]);
      g.add(mesh);
      XR[i] = { obj: g, mesh, r: d.r, orbit: d.orbit };
      if (d.key !== 'ceres') {
        // the orbit, tilted and stretched as it really is
        const pts = [];
        for (let s = 0; s <= 240; s++) {
          const o = { ...d.orbit, a0: s / 240 * Math.PI * 2 };
          pts.push(dwarfPos(o, 0, new THREE.Vector3()));
        }
        scene.add(new THREE.LineLoop(new THREE.BufferGeometry().setFromPoints(pts), dwarfOrbitMat));
      }
    } else if (d.kind === 'craft') {
      const g = voyagerModel();
      const pos = eclDir(d.dir[0], d.dir[1]).multiplyScalar(auToScene(d.au));
      g.position.copy(pos); g.lookAt(0, 0, 0); scene.add(g);
      const path = new THREE.Line(new THREE.BufferGeometry().setFromPoints([pos.clone().setLength(SUN_R * 2), pos]), craftPathMat);
      path.computeLineDistances(); scene.add(path);
      XR[i] = { obj: g, r: 0.6 };
    }
  });
  buildKuiper();
  buildHeliosphere();
  buildOort();
  XR[KUIPER] = { center: new THREE.Vector3(0, 0, 0), r: 118 };
  XR[HELIO] = { center: HELIO_NOSE.clone().multiplyScalar(-110), r: 270 };
  XR[OORT] = { center: new THREE.Vector3(0, 0, 0), r: 495 };
}
const dwarfOrbitMat = new THREE.LineBasicMaterial({ color: 0xb59fe0, transparent: true, opacity: 0.2, depthWrite: false });
const craftPathMat = new THREE.LineDashedMaterial({ color: 0xb8c6ff, dashSize: 2.5, gapSize: 2.5, transparent: true, opacity: 0.35, depthWrite: false });

function voyagerModel() {
  const g = new THREE.Group();
  const white = new THREE.MeshStandardMaterial({ color: 0xe4e2dc, roughness: 0.45, metalness: 0.3, side: THREE.DoubleSide });
  const gold = new THREE.MeshStandardMaterial({ color: 0xc9a24a, roughness: 0.35, metalness: 0.7 });
  const dark = new THREE.MeshStandardMaterial({ color: 0x45464a, roughness: 0.6, metalness: 0.4 });
  // the big dish faces home: +z points at the Sun after lookAt
  const dish = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.1, 0.1, 32, 1, true), white);
  dish.rotation.x = Math.PI / 2; dish.position.z = 0.12; g.add(dish);
  const bus = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 0.14, 10), gold);
  bus.rotation.x = Math.PI / 2; g.add(bus);
  const rod = (len, ang, mat, w = 0.012) => {
    const m = new THREE.Mesh(new THREE.CylinderGeometry(w, w, len, 6), mat);
    m.rotation.z = ang; m.position.set(Math.cos(ang - Math.PI / 2) * -len / 2, Math.sin(ang - Math.PI / 2) * -len / 2, -0.05);
    g.add(m); return m;
  };
  rod(1.0, Math.PI / 2 + 0.15, dark, 0.018);          // the power boom
  const rtg = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 0.22, 8), dark);
  rtg.position.set(-0.99, -0.15, -0.05); rtg.rotation.z = Math.PI / 2; g.add(rtg);
  rod(0.8, -Math.PI / 2 - 0.2, white, 0.014);          // the science boom
  rod(2.2, Math.PI / 4, dark, 0.006);                  // the long magnetometer boom
  return g;
}

function buildKuiper() {
  const n = IS_MOBILE ? 2500 : 5000, pos = new Float32Array(n * 3), col = new Float32Array(n * 3);
  const g3 = () => rng() + rng() + rng() - 1.5;
  for (let s = 0; s < n; s++) {
    const u = rng();
    // the cold classical belt, the plutinos locked to Neptune, and the scattered disc
    const au = u < 0.6 ? 44 + g3() * 3.2 : u < 0.8 ? 39.4 + (rng() - 0.5) * 1.4 : 31 + rng() * rng() * 60;
    const inc = (u < 0.6 ? 2.5 : 13) * g3() * Math.PI / 180, lon = rng() * Math.PI * 2, R = auToScene(au);
    pos.set([Math.cos(lon) * Math.cos(inc) * R, Math.sin(inc) * R, -Math.sin(lon) * Math.cos(inc) * R], s * 3);
    const b = 0.45 + rng() * 0.55, red = rng() < 0.55;
    col.set(red ? [0.85 * b, 0.66 * b, 0.55 * b] : [0.72 * b, 0.8 * b, 0.92 * b], s * 3);
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setAttribute('color', new THREE.BufferAttribute(col, 3));
  kuiperPts = new THREE.Points(g, new THREE.PointsMaterial({ size: IS_MOBILE ? 1.6 : 1.8, sizeAttenuation: false, vertexColors: true, transparent: true, opacity: 0.85, depthWrite: false }));
  scene.add(kuiperPts);
}
function buildOort() {
  const n = IS_MOBILE ? 3000 : 6000, pos = new Float32Array(n * 3), col = new Float32Array(n * 3);
  for (let s = 0; s < n; s++) {
    const R = 300 + 195 * Math.pow(rng(), 0.8), u = rng() * 2 - 1, th = rng() * Math.PI * 2, sq = Math.sqrt(1 - u * u);
    const flat = R < 380 ? 0.55 : 1; // the inner (Hills) cloud is flatter, nearer the planets' plane
    pos.set([sq * Math.cos(th) * R, u * R * flat, sq * Math.sin(th) * R], s * 3);
    const b = 0.4 + rng() * 0.6;
    col.set([0.75 * b, 0.82 * b, 1.0 * b], s * 3);
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setAttribute('color', new THREE.BufferAttribute(col, 3));
  oortPts = new THREE.Points(g, new THREE.PointsMaterial({ size: 1.4, sizeAttenuation: false, vertexColors: true, transparent: true, opacity: 0, depthWrite: false }));
  scene.add(oortPts);
}
/* the termination shock and the heliopause: round at the nose, drawn out into a tail */
function buildHeliosphere() {
  [[90, [0.42, 0.62, 1.0], 0.9], [120, [0.72, 0.46, 1.0], 1.1]].forEach(([noseAU, color, strength]) => {
    const geo = new THREE.SphereGeometry(1, 72, 48), p = geo.attributes.position, v = new THREE.Vector3();
    for (let s = 0; s < p.count; s++) {
      v.fromBufferAttribute(p, s).normalize();
      // the tail is stretched after the squeeze, or the logarithm would round the bubble off
      const c = v.dot(HELIO_NOSE), f = 1 + 1.4 * Math.pow((1 - c) / 2, 2.4);
      v.multiplyScalar(auToScene(noseAU) * f);
      p.setXYZ(s, v.x, v.y, v.z);
    }
    geo.computeVertexNormals();
    const mat = new THREE.ShaderMaterial({
      uniforms: { uColor: { value: new THREE.Color(...color) }, uS: { value: strength }, uFade: { value: 0 }, uTime: sunMat.uniforms.uTime, uNose: { value: HELIO_NOSE } },
      vertexShader: `varying vec3 vN; varying vec3 vV; varying vec3 vW;
        void main(){ vec4 wp = modelMatrix*vec4(position,1.); vW = wp.xyz; vN = normalize(mat3(modelMatrix)*normal); vV = normalize(cameraPosition-wp.xyz); gl_Position = projectionMatrix*viewMatrix*wp; }`,
      fragmentShader: `uniform vec3 uColor; uniform float uS; uniform float uFade; uniform float uTime; uniform vec3 uNose; varying vec3 vN; varying vec3 vV; varying vec3 vW;
        void main(){
          float rim = pow(1.0 - abs(dot(normalize(vN), normalize(vV))), 2.4);
          float flow = 0.8 + 0.2 * sin(dot(vW, uNose) * 0.09 + uTime * 0.6);   // ripples drifting down the tail
          gl_FragColor = vec4(uColor * rim * flow * uS * uFade * 0.32, 1.0);
        }`,
      transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide,
    });
    const mesh = new THREE.Mesh(geo, mat);
    scene.add(mesh);
    helioShells.push(mesh);
  });
}

function updateExtras(t, real, days) {
  for (const k in XR) {
    const x = XR[k], i = +k, d = BODIES[i];
    if (d.kind === 'moon' && !d.existing) {
      // in real time a moon goes round at its true rate (its starting point is not real)
      const a = real ? x.a0 + days / d.P * Math.PI * 2 : x.a0 + x.w * t;
      x.obj.position.set(Math.cos(a) * x.R, 0, -Math.sin(a) * x.R);
      x.obj.rotation.y = a + Math.PI; // the same face always turned to its planet
    } else if (d.kind === 'dwarf') {
      if (real) dwarfPos({ ...x.orbit, a0: x.orbit.a0 + days / (x.orbit.T * 365.25) * Math.PI * 2 }, 0, x.obj.position);
      else dwarfPos(x.orbit, t, x.obj.position);
      x.mesh.rotation.y = t * (d.key === 'haumea' ? 1.6 : 0.3);
    }
  }
  if (kuiperPts) kuiperPts.rotation.y = t * 0.004;
}
/* the outer layers come in as the camera pulls back, and stay out of the way up close */
function fadeExtras() {
  const cd = camera.position.length();
  const hf = smooth(430, 720, cd);
  helioShells.forEach((m) => { m.material.uniforms.uFade.value = hf; m.visible = hf > 0.001; });
  if (oortPts) { oortPts.material.opacity = 0.8 * smooth(1000, 1400, cd); oortPts.visible = oortPts.material.opacity > 0.001; }
}

/* photographs for the moons and Pluto, fetched once the scene is up so they never delay it */
function loadMoonTextures() {
  EXTRAS.forEach((d) => {
    if (!d.tex) return;
    const x = XR[KEY_IDX[d.key]];
    const mesh = x.mesh || x.obj;
    fetchTex(d.tex, true).then((tex) => {
      const rocky = d.paint === 'rocky';
      swapMaps(mesh.material, { map: tex, bumpMap: rocky ? tex : null });
      if (rocky) mesh.material.bumpScale = 1.2;
    }, () => {});
  });
}

/* ---------------- tour camera (pure function of t) ---------------- */
const UP = new THREE.Vector3(0, 1, 0);
// scratch objects reused every frame (no allocations in the render loop)
const _P = new THREE.Vector3(), _dir = new THREE.Vector3(), _side = new THREE.Vector3(), _off = new THREE.Vector3();
const _prev = { p: new THREE.Vector3(), q: new THREE.Vector3() }, camState = { p: new THREE.Vector3(), q: new THREE.Vector3() };
/* where the camera sits relative to a planet: on the lit side, a little above */
function planetView(pl, P, out) {
  _dir.copy(P).normalize(); _side.set(-_dir.z, 0, _dir.x);
  return out.copy(_dir).multiplyScalar(-0.65).addScaledVector(_side, 0.85).addScaledVector(UP, pl.ring ? 0.75 : 0.3).normalize();
}
const viewDist = (pl) => (pl.ring ? pl.r * 2.35 : pl.key === 'earth' ? 1.9 : pl.r) * (pl.ring ? 3.3 : 4.2);
function shotCam(j, t, o) {
  const sh = SHOTS[j], lt = t - sh.s, len = sh.e - sh.s;
  if (sh.type === 'wide0') {
    const k = ease(lt / len), ang = 0.6 + 0.05 * t, r = 175 - 45 * k, h = 105 - 50 * k;
    o.p.set(Math.cos(ang) * r, h, Math.sin(ang) * r); o.q.set(0, -6 * k, 0); return o;
  }
  if (sh.type === 'sun') {
    const ang = 1.0 + 0.07 * lt, r = 27 - 4 * lt / len;
    o.p.set(Math.cos(ang) * r, 5 + lt * 0.2, Math.sin(ang) * r); o.q.set(0, 0, 0); return o;
  }
  if (sh.type === 'planet') {
    const pl = PLANETS[sh.i]; planetPos(sh.i, t, _P);
    const d = viewDist(pl) * (1 - 0.14 * lt / len);
    _dir.copy(_P).normalize(); _side.set(-_dir.z, 0, _dir.x);
    _off.copy(_dir).multiplyScalar(-0.65).addScaledVector(_side, 0.85 - 0.12 * lt / len).addScaledVector(UP, pl.ring ? 0.75 : 0.3).normalize();
    o.p.copy(_P).addScaledVector(_off, d); o.q.copy(_P); return o;
  }
  // wide1 finale
  const k = ease(lt / len), ang = 2.3 + 0.05 * lt, r = 70 + 120 * k, h = 22 + 110 * k;
  o.p.set(Math.cos(ang) * r, h, Math.sin(ang) * r); o.q.set(0, 0, 0); return o;
}
const TRANS = 2.0;
function cameraAt(t, o) {
  const j = shotAt(t), lt = t - SHOTS[j].s;
  shotCam(j, t, o);
  if (j > 0 && lt < TRANS) {
    const A = shotCam(j - 1, t, _prev), k = ease(lt / TRANS), lift = Math.sin(Math.PI * k) * A.p.distanceTo(o.p) * 0.22;
    o.p.lerpVectors(A.p, o.p, k).addScaledVector(UP, lift);
    o.q.lerpVectors(A.q, o.q, k);
  }
  // a tall, narrow screen (a phone held upright) needs the camera further back to keep the frame
  o.p.sub(o.q).multiplyScalar(fit()).add(o.q);
  return o;
}
const fit = () => clamp(1.3 / camera.aspect, 1, 1.9);

/* ---------------- state ---------------- */
let mode = 'explore';          // 'explore' | 'tour'
let worldT = 0;                // drives the planets
let fxT = 0;                   // the sun's surface keeps moving in explore mode
/* the real-time button: in motion it offers real time; in real time it shows the clock,
   and either way a press flies to Earth with the reader's own part of the world facing them */
const nowEl = document.createElement('button');
nowEl.type = 'button';
nowEl.className = 'bbs__now';
nowEl.title = T.realTitle;
nowEl.setAttribute('aria-label', T.realTitle);
root.appendChild(nowEl);
// until the reader has tried it once, the button pulses gently to be noticed
let realTried = false;
try { realTried = localStorage.getItem('bb_solar_real') === '1'; } catch (e) { /* storage blocked */ }
nowEl.classList.toggle('is-call', !realTried);
const nowTip = document.createElement('span');
nowTip.className = 'bbs__nowtip';
nowTip.setAttribute('aria-hidden', 'true');
nowTip.textContent = T.realHint;
nowTip.hidden = true;
root.appendChild(nowTip);
function hideNowTip() {
  nowTip.classList.remove('is-on');
  setTimeout(() => { nowTip.hidden = true; }, 400);
}
// a first visit also gets a small bubble saying what the button does
function showNowTip() {
  if (realTried || mode !== 'explore') return;
  nowTip.hidden = false;
  // beside the button on wide screens; under it (from the stylesheet) on phones
  nowTip.style.left = matchMedia('(max-width: 600px)').matches ? '' : (nowEl.offsetLeft + nowEl.offsetWidth + 14) + 'px';
  requestAnimationFrame(() => nowTip.classList.add('is-on'));
  setTimeout(hideNowTip, 10000);
}
nowEl.addEventListener('click', () => {
  realTried = true;
  hideNowTip();
  nowEl.classList.remove('is-call');
  try { localStorage.setItem('bb_solar_real', '1'); } catch (e) { /* storage blocked */ }
});
/* The site's own fixed bars (its sticky menu, the admin bar) can cover the top of the scene, and on a short
   or very wide screen the scene's bottom can run past the window. The toolbar, the notes and the chips
   move into whatever part of the scene is in view, so they are always there to press. */
function coverBottom(x) {
  let y = 0;
  for (let k = 0; k < 4; k++) {
    let hit = 0;
    for (const el of document.elementsFromPoint(x, y + 1)) {
      if (root.contains(el)) break;
      for (let e = el; e && e !== document.body && e !== document.documentElement; e = e.parentElement) {
        const p = getComputedStyle(e).position;
        if (p === 'fixed' || p === 'sticky') { hit = Math.max(hit, e.getBoundingClientRect().bottom); break; }
      }
    }
    if (hit <= y + 1) break;
    y = hit;
  }
  return y;
}
let safeT = -1, safeB = -1, safeAsked = false;
function updateSafe() {
  safeAsked = false;
  let t = 0, b = 0;
  const full = document.fullscreenElement || document.webkitFullscreenElement || root.classList.contains('is-pseudo-fs');
  const r = root.getBoundingClientRect(), vh = window.innerHeight;
  if (!full && r.bottom > 0 && r.top < vh) {
    const x = clamp(r.left + r.width / 2, 1, window.innerWidth - 1);
    t = clamp(coverBottom(x) - r.top, 0, r.height - 220);
    b = clamp(r.bottom - vh, 0, Math.max(0, r.height - 220 - t));
  }
  t = Math.round(t); b = Math.round(b);
  if (t !== safeT) { safeT = t; root.style.setProperty('--bbs-safe-top', t + 'px'); }
  if (b !== safeB) { safeB = b; root.style.setProperty('--bbs-safe-bottom', b + 'px'); }
}
const askSafe = () => { if (!safeAsked) { safeAsked = true; requestAnimationFrame(updateSafe); } };
addEventListener('scroll', askSafe, { passive: true });
addEventListener('resize', askSafe);
document.addEventListener('fullscreenchange', askSafe);
if (window.ResizeObserver) new ResizeObserver(askSafe).observe(root);
askSafe();
const EN_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const BN_MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
/* "৮ অক্টোবর, বিকেল ৩:৪৭": the way the time is said in Bengali, not a translated English clock */
function nowText(d) {
  const h = d.getHours(), m = String(d.getMinutes()).padStart(2, '0');
  // "8 October, 4:15 PM": the twelve-hour clock in English too
  if (EN) return d.getDate() + ' ' + EN_MONTHS[d.getMonth()] + ', ' + (h % 12 || 12) + ':' + m + (h < 12 ? ' AM' : ' PM');
  const part = h < 4 ? 'রাত' : h < 6 ? 'ভোর' : h < 12 ? 'সকাল' : h < 15 ? 'দুপুর' : h < 18 ? 'বিকেল' : h < 20 ? 'সন্ধ্যা' : 'রাত';
  return bn(d.getDate() + ' ' + BN_MONTHS[d.getMonth()] + ', ' + part + ' ' + (h % 12 || 12) + ':' + m);
}
let nowShown = '';
function updateNow() {
  const txt2 = realNow() ? (EN ? 'Live · ' : 'এখন · ') + nowText(new Date()) : '🕒 ' + T.realTime;
  if (txt2 !== nowShown) { nowEl.textContent = txt2; nowShown = txt2; }
  nowEl.classList.toggle('is-live', realNow());
  nowEl.hidden = mode !== 'explore' || !ready;
}
/* Where the reader is, roughly: from the device's time zone, so no permission is asked and
   nothing leaves the page. A known zone gives its city; any other, its longitude from the offset. */
const YOU = (() => {
  let tz = '';
  try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { /* no Intl */ }
  const Z = {
    'Asia/Dhaka': [23.8, 90.4], 'Asia/Kolkata': [22.6, 88.4], 'Asia/Calcutta': [22.6, 88.4], 'Asia/Karachi': [24.9, 67.0], 'Asia/Kathmandu': [27.7, 85.3],
    'Asia/Colombo': [6.9, 79.9], 'Asia/Yangon': [16.8, 96.2], 'Asia/Bangkok': [13.8, 100.5], 'Asia/Kuala_Lumpur': [3.1, 101.7], 'Asia/Singapore': [1.35, 103.8],
    'Asia/Jakarta': [-6.2, 106.8], 'Asia/Manila': [14.6, 121.0], 'Asia/Hong_Kong': [22.3, 114.2], 'Asia/Shanghai': [31.2, 121.5], 'Asia/Seoul': [37.6, 127.0],
    'Asia/Tokyo': [35.7, 139.7], 'Asia/Dubai': [25.2, 55.3], 'Asia/Riyadh': [24.7, 46.7], 'Asia/Qatar': [25.3, 51.5], 'Asia/Kuwait': [29.4, 48.0],
    'Asia/Muscat': [23.6, 58.4], 'Asia/Bahrain': [26.2, 50.6], 'Asia/Tehran': [35.7, 51.4], 'Asia/Kabul': [34.5, 69.2], 'Europe/Istanbul': [41.0, 29.0],
    'Europe/Moscow': [55.8, 37.6], 'Europe/London': [51.5, -0.1], 'Europe/Dublin': [53.3, -6.3], 'Europe/Paris': [48.9, 2.35], 'Europe/Berlin': [52.5, 13.4],
    'Europe/Rome': [41.9, 12.5], 'Europe/Madrid': [40.4, -3.7], 'Europe/Amsterdam': [52.4, 4.9], 'Europe/Stockholm': [59.3, 18.1], 'Africa/Cairo': [30.0, 31.2],
    'Africa/Lagos': [6.5, 3.4], 'Africa/Nairobi': [-1.3, 36.8], 'Africa/Johannesburg': [-26.2, 28.0], 'America/New_York': [40.7, -74.0], 'America/Toronto': [43.7, -79.4],
    'America/Chicago': [41.9, -87.6], 'America/Denver': [39.7, -105.0], 'America/Los_Angeles': [34.1, -118.2], 'America/Vancouver': [49.3, -123.1], 'America/Mexico_City': [19.4, -99.1],
    'America/Sao_Paulo': [-23.6, -46.6], 'America/Buenos_Aires': [-34.6, -58.4], 'America/Argentina/Buenos_Aires': [-34.6, -58.4], 'Australia/Sydney': [-33.9, 151.2], 'Australia/Melbourne': [-37.8, 145.0],
    'Australia/Brisbane': [-27.5, 153.0], 'Australia/Perth': [-31.95, 115.9], 'Australia/Adelaide': [-34.9, 138.6], 'Pacific/Auckland': [-36.8, 174.8],
  };
  const ll = Z[tz] || [20, -new Date().getTimezoneOffset() / 4];
  const la = ll[0] * Math.PI / 180, lo = ll[1] * Math.PI / 180;
  // Earth's own frame: +x Greenwich, −z 90° east, +y north
  return new THREE.Vector3(Math.cos(la) * Math.cos(lo), Math.sin(la), -Math.cos(la) * Math.sin(lo));
})();
const _yq = new THREE.Quaternion(), _yw = new THREE.Vector3(), _ye = new THREE.Vector3();
let youDot = null, youTag = null;
function buildYou() {
  const earth = bodies[2];
  youDot = new THREE.Mesh(new THREE.SphereGeometry(0.016, 12, 8), new THREE.MeshBasicMaterial({ color: 0xffc23d }));
  youDot.position.copy(YOU).multiplyScalar(1.02);
  const glow = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex([[0, 'rgba(255,214,120,1)'], [0.35, 'rgba(255,183,3,0.5)'], [1, 'rgba(255,160,0,0)']]), blending: THREE.AdditiveBlending, depthWrite: false, transparent: true }));
  glow.scale.setScalar(0.11);
  youDot.add(glow);
  youDot.visible = false;
  earth.mesh.add(youDot);
  youTag = document.createElement('span');
  youTag.className = 'bbs-tag bbs-tag--you';
  youTag.textContent = T.you;
  tagsEl.appendChild(youTag);
}
function goReal() {
  if (!ready) return;
  closeCard();
  hideHint();
  if (mode === 'tour') return;
  if (fast) { fast = false; syncOrbitsButton(); }
  // turn Earth to the real moment first, then aim the camera down at the reader's place
  updateWorld(worldT, fxT);
  const e = bodies[2].mesh;
  e.updateWorldMatrix(true, false);
  const dir = YOU.clone().applyQuaternion(e.getWorldQuaternion(_yq)).normalize();
  flyTo(3, { off: dir.add(_ye.set(0, 0.12, 0)).normalize().multiplyScalar(4.4 * fit()) });
  updateNow();
  setTimeout(showFastTip, 1400);
}
nowEl.addEventListener('click', goReal);
/* Real time stands still, so each time it starts, a note under the ⏩ button (which glows meanwhile)
   says how to set the planets moving again. */
const fastTip = document.createElement('button');
fastTip.type = 'button';
fastTip.className = 'bbs__fasttip';
fastTip.textContent = T.fastHint;
fastTip.hidden = true;
root.appendChild(fastTip);
let fastTipTimer = 0;
function hideFastTip() {
  clearTimeout(fastTipTimer);
  $('orbits').classList.remove('is-nudge');
  if (fastTip.hidden) return;
  fastTip.classList.remove('is-on');
  setTimeout(() => { if (!fastTip.classList.contains('is-on')) fastTip.hidden = true; }, 400);
}
function showFastTip() {
  if (fast || mode !== 'explore') return;
  hideSoundTip();
  const b = $('orbits').getBoundingClientRect(), r = root.getBoundingClientRect();
  fastTip.style.top = (b.bottom - r.top + 12) + 'px';
  fastTip.style.right = (r.right - b.right) + 'px';
  fastTip.hidden = false;
  // on a narrow screen it would cover the clock: then it drops below it
  const t = fastTip.getBoundingClientRect(), n = nowEl.getBoundingClientRect();
  if (t.left < n.right && t.top < n.bottom) fastTip.style.top = (n.bottom - r.top + 12) + 'px';
  $('orbits').classList.add('is-nudge');
  requestAnimationFrame(() => fastTip.classList.add('is-on'));
  clearTimeout(fastTipTimer);
  fastTipTimer = setTimeout(hideFastTip, 8000);
}
// the note itself does what it says
fastTip.addEventListener('click', () => $('orbits').click());
let tourT = 0, playing = false, speed = 1, loop = false;
let free = false;              // tour paused and the viewer has taken the camera
let dirty = true, ready = false, visible = true, seeking = false;
let focus = -1;                // -1 whole system, 0 sun, 1..8 planets
let fly = null;
let cardIdx = -1;
const lastFocus = new THREE.Vector3();

/* ---------------- free look ---------------- */
// Registered before OrbitControls so it runs first: once the view is as far out
// as it goes, the wheel scrolls the page instead of being swallowed by the scene.
renderer.domElement.addEventListener('wheel', (e) => {
  if (!controls.enabled) return;
  const d = camera.position.distanceTo(controls.target);
  if (e.deltaY > 0 && d >= controls.maxDistance * 0.995) e.stopImmediatePropagation();
}, { passive: true });

const controls = new OrbitControls(camera, renderer.domElement);
Object.assign(controls, { enableDamping: true, dampingFactor: 0.08, minDistance: 4, maxDistance: 600, rotateSpeed: IS_MOBILE ? 0.9 : 0.7, zoomSpeed: 0.9 });
controls.addEventListener('start', () => {
  hideHint();
  if (mode === 'tour' && !free) { free = true; updateUI(); }
  dirty = true;
});
controls.addEventListener('change', () => { dirty = true; });

/* any leftover damping spin would fight a scripted flight, so spend it at once */
function flushControls() {
  controls.enableDamping = false; controls.update(); controls.enableDamping = true;
}
const interactive = () => mode === 'explore' || !playing;
function syncControls() { controls.enabled = interactive() && !fly; }

/* ---------------- focus, flights and following ---------------- */
const OVERVIEW_Q = new THREE.Vector3(0, -3, 0);
const _L = new THREE.Vector3(), _goal = new THREE.Vector3(), _d = new THREE.Vector3();
/* the belt is a ring: a flight goes to the stretch of it nearest the camera */
const beltSpot = new THREE.Vector3();
let beltPicked = false;
function nearestBeltSpot(out) {
  const az = Math.atan2(camera.position.z, camera.position.x);
  return out.set(Math.cos(az) * BELT_R, 0, Math.sin(az) * BELT_R);
}
function focusPoint(idx, out) {
  if (idx < 0) return out.copy(OVERVIEW_Q);
  return bodyWorld(idx, out);
}
function focusOffset(idx, L, out) {
  const az = Math.atan2(camera.position.z - controls.target.z, camera.position.x - controls.target.x);
  if (idx < 0) return out.set(Math.cos(az) * 98, 66, Math.sin(az) * 98).multiplyScalar(fit());
  if (idx === 0) return out.set(Math.cos(az), 0.28, Math.sin(az)).normalize().multiplyScalar(27 * fit());
  if (idx === BELT) {
    // a little inside the ring and above it, so the rocks are lit and the Sun sits off to one side
    _dir.copy(L).normalize(); _side.set(-_dir.z, 0, _dir.x);
    return out.copy(_dir).multiplyScalar(-7).addScaledVector(_side, 14).addScaledVector(UP, 7.5).multiplyScalar(fit());
  }
  if (idx === KUIPER) return out.set(Math.cos(az) * 235, 175, Math.sin(az) * 235).multiplyScalar(fit());
  if (idx === OORT) return out.set(Math.cos(az) * 1000, 640, Math.sin(az) * 1000).multiplyScalar(fit());
  if (idx === HELIO) { _side.crossVectors(HELIO_NOSE, UP).normalize(); return out.copy(_side).multiplyScalar(620).addScaledVector(UP, 260).multiplyScalar(fit()); }
  const kd = kindOf(idx);
  if (kd === 'moon' || kd === 'dwarf') return planetView({}, L, out).multiplyScalar(Math.max(BODIES[idx].r * 5.5, 0.6) * fit());
  if (kd === 'craft') return planetView({}, L, out).multiplyScalar(3.2 * fit());
  const pl = PLANETS[idx - 1];
  return planetView(pl, L, out).multiplyScalar(viewDist(pl) * fit());
}
function setLimits(idx) {
  const kd = kindOf(idx);
  // pulling back further is for the outer regions; nearer in, the wheel soon hands back to the page
  controls.maxDistance = idx === KUIPER ? 900 : idx === HELIO ? 1500 : idx === OORT ? 2400 : 700;
  controls.minDistance = idx < 0 ? 4 : idx === 0 ? SUN_R * 1.35 : idx === BELT ? 1.5 : kd === 'region' ? 30 : kd === 'craft' ? 0.6
    : (kd === 'moon' || kd === 'dwarf') ? BODIES[idx].r * 1.5 : PLANETS[idx - 1].r * (PLANETS[idx - 1].ring ? 1.6 : 1.45);
}
function flyTo(idx, opts = {}) {
  flushControls();
  // a click on the belt flies to where it landed; a chip, to the stretch nearest the camera
  if (idx === BELT && !beltPicked) nearestBeltSpot(beltSpot);
  beltPicked = false;
  const L = focusPoint(idx, _L);
  const off = opts.off ? opts.off.clone() : focusOffset(idx, L, new THREE.Vector3());
  const fromP = opts.from ? opts.from.clone() : camera.position.clone();
  _goal.copy(L).add(off);
  const dur = REDUCED ? 0.01 : (opts.dur || clamp(1 + fromP.distanceTo(_goal) / 140, 1.2, 2.6));
  fly = { idx, t: 0, dur, fromP, fromQ: controls.target.clone(), off };
  if (!opts.dur) sfxWhoosh(dur);
  focus = idx;
  syncHome();
  setLimits(idx);
  syncControls();
  dirty = true;
}
function stepFly(dt) {
  fly.t += dt;
  const k = easeInOut(fly.t / fly.dur);
  const L = focusPoint(fly.idx, _L);
  _goal.copy(L).add(fly.off);
  const lift = Math.sin(Math.PI * k) * fly.fromP.distanceTo(_goal) * 0.12;
  camera.position.lerpVectors(fly.fromP, _goal, k).addScaledVector(UP, lift);
  controls.target.lerpVectors(fly.fromQ, L, k);
  camera.lookAt(controls.target);
  if (fly.t >= fly.dur) {
    lastFocus.copy(L);
    fly = null;
    syncControls();
  }
}
/* keep a chosen planet in frame while it travels along its orbit: the view
   turns with the planet around the sun, so its lit face stays towards us */
const _o = new THREE.Vector3(), _t = new THREE.Vector3();
function follow() {
  if (fly || focus < 1) return;
  const kd = kindOf(focus);
  if (kd === 'moon' || kd === 'dwarf') {
    // a moon or a dwarf planet: move with it
    focusPoint(focus, _L);
    _d.subVectors(_L, lastFocus);
    if (_d.lengthSq() > 0) { camera.position.add(_d); controls.target.add(_d); }
    lastFocus.copy(_L);
    return;
  }
  if (!isPlanet(focus)) return;
  focusPoint(focus, _L);
  const dA = Math.atan2(-_L.z, _L.x) - Math.atan2(-lastFocus.z, lastFocus.x);
  if (dA) {
    _o.subVectors(camera.position, controls.target).applyAxisAngle(UP, dA);
    _t.subVectors(controls.target, lastFocus).applyAxisAngle(UP, dA);
    controls.target.copy(_L).add(_t);
    camera.position.copy(controls.target).add(_o);
  }
  lastFocus.copy(_L);
}

/* ---------------- labels, chips and the info card ---------------- */
const tagsEl = $('tags'), chipsEl = $('chips'), card = $('card');
const tagEls = BODIES.map((d, k) => {
  const b = document.createElement('button');
  b.type = 'button'; b.className = 'bbs-tag'; b.textContent = txt(d).name; b.dataset.i = k;
  b.setAttribute('aria-label', txt(d).name + T.showInfo); b.tabIndex = -1;
  tagsEl.appendChild(b); return b;
});
tagsEl.addEventListener('click', (e) => { const b = e.target.closest('.bbs-tag'); if (b) select(+b.dataset.i); });
const chipEls = ORDER.map((k) => {
  const d = BODIES[k];
  const b = document.createElement('button');
  b.type = 'button'; b.className = 'bbs-chip'; b.dataset.i = k;
  b.innerHTML = '<span class="bbs-chip__dot" aria-hidden="true"></span>';
  b.firstChild.style.background = d.color;
  b.appendChild(document.createTextNode(txt(d).name));
  chipsEl.appendChild(b); return b;
});
chipsEl.addEventListener('click', (e) => { const b = e.target.closest('.bbs-chip'); if (b) select(+b.dataset.i); });

function navFor(idx) {
  if (ORDER.includes(idx)) return ORDER;
  const kd = kindOf(idx);
  if (kd === 'moon') return moonsOf(BODIES[idx].parent);
  if (kd === 'dwarf') return DWARFS;
  if (kd === 'craft') return CRAFT;
  return [idx];
}
function kickerFor(idx) {
  const d = BODIES[idx];
  if (idx === 0) return T.centre;
  if (idx === BELT) return T.between;
  if (d.kind === 'planet') return T.planetOf(idx);
  if (d.kind === 'moon') return T.moonOf(txt(BODIES[parentIdx(idx)]).name);
  return EN ? EN_X[d.key].kick : d.kick;
}
/* rows of buttons under the facts: a planet's moons, what lies in a region, the way back */
function relFor(idx) {
  const d = BODIES[idx], rows = [];
  if (d.kind === 'planet' || d.kind === 'dwarf') { const m = moonsOf(d.key); if (m.length) rows.push([T.relMoons, m]); }
  if (idx === BELT) rows.push([T.relHere, [KEY_IDX.ceres]]);
  if (d.rel) rows.push([T.relHere, d.rel.map((k) => KEY_IDX[k])]);
  if (d.kind === 'moon') rows.push([T.relBack, [parentIdx(idx)]]);
  if (d.home) rows.push([T.relBack, [KEY_IDX[d.home]]]);
  return rows;
}
function openCard(idx) {
  const list = navFor(idx), pos = list.indexOf(idx);
  const pi = list[(pos + list.length - 1) % list.length], ni = list[(pos + 1) % list.length];
  const d = BODIES[idx], x = txt(d), prev = txt(BODIES[pi]), next = txt(BODIES[ni]);
  const rel = relFor(idx).map(([h, ids]) => `<div class="bbs-card__rel"><span class="bbs-card__rel-h">${h}</span>` +
    ids.map((i) => `<button type="button" data-i="${i}"><span class="bbs-card__reldot" style="background:${BODIES[i].color}" aria-hidden="true"></span>${txt(BODIES[i]).name}</button>`).join('') + '</div>').join('');
  card.innerHTML =
    `<button type="button" class="bbs-card__close" data-c="close" aria-label="${T.closeCard}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6z"/></svg></button>` +
    `<p class="bbs-card__kicker">${kickerFor(idx)}</p>` +
    `<h2 class="bbs-card__name" id="bbs-card-title"><span class="bbs-card__dot" style="background:${d.color}" aria-hidden="true"></span>${x.name} <span class="bbs-card__en" lang="${x.altLang}">${x.alt}</span></h2>` +
    `<p class="bbs-card__note">${x.note}</p>` +
    '<dl class="bbs-card__facts">' + x.facts.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('') + '</dl>' +
    rel +
    (list.length > 1 ? '<div class="bbs-card__nav">' +
    `<button type="button" data-c="prev" data-i="${pi}"><span aria-hidden="true">‹</span> ${prev.name}</button>` +
    `<button type="button" data-c="next" data-i="${ni}">${next.name} <span aria-hidden="true">›</span></button>` +
    '</div>' : '');
  card.hidden = false;
  card.scrollTop = 0;
  hideSoundTip();
  sfxChime();
  cardIdx = idx;
  chipEls.forEach((c) => c.setAttribute('aria-pressed', +c.dataset.i === idx ? 'true' : 'false'));
}
function closeCard() {
  if (cardIdx < 0) return;
  card.hidden = true; cardIdx = -1;
  chipEls.forEach((c) => c.setAttribute('aria-pressed', 'false'));
}
card.addEventListener('click', (e) => {
  const b = e.target.closest('button'); if (!b) return;
  if (b.dataset.c === 'close') closeCard();
  else if (b.dataset.i) select(+b.dataset.i);
});

function select(idx) {
  if (!ready) return;
  if (mode === 'tour') {
    if (playing) pause();
    free = true;
  }
  hideHint();
  flyTo(idx);
  openCard(idx);
  updateUI();
}
/* the wide button: out to the whole heliosphere, and from any of the outer views back to the planets */
const isWideView = () => focus === KUIPER || focus === HELIO || focus === OORT;
function goHome() {
  closeCard();
  if (mode === 'tour') { free = true; updateUI(); }
  flyTo(isWideView() ? -1 : HELIO);
}
function syncHome() {
  const b = $('home'), wide = isWideView();
  b.querySelector('span').textContent = wide ? b.dataset.near : b.dataset.wide;
  b.title = wide ? b.dataset.nearTitle : b.dataset.wideTitle;
  b.setAttribute('aria-label', b.title);
}

/* screen-space picking: generous on touch, so a tiny Mercury can still be tapped */
const _s = new THREE.Vector3();
function pick(cx, cy) {
  const rect = renderer.domElement.getBoundingClientRect();
  const x = cx - rect.left, y = cy - rect.top, h = rect.height, w = rect.width;
  const tanH = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
  const minHit = COARSE ? 28 : 16;
  let best = -1, bestScore = Infinity;
  for (let k = 0; k < BODIES.length; k++) {
    const kd = kindOf(k);
    if (kd === 'belt' || kd === 'region') continue;
    let R;
    if (k === 0) R = SUN_R;
    else if (isPlanet(k)) R = PLANETS[k - 1].r * (PLANETS[k - 1].ring ? 1.9 : 1.15);
    else {
      R = (BODIES[k].r || 0.5) * 1.3;
      if (kd === 'moon') {
        // a moon can only be picked once its planet is close
        const p = parentIdx(k), pr = isPlanet(p) ? PLANETS[p - 1].r : BODIES[p].r;
        if (camera.position.distanceTo(bodyWorld(p, _s)) > pr * 30) continue;
      }
    }
    focusPoint(k, _s);
    const dist = camera.position.distanceTo(_s);
    _s.project(camera);
    if (_s.z > 1) continue;
    const sx = (_s.x + 1) / 2 * w, sy = (1 - _s.y) / 2 * h;
    const rad = Math.max(R / (dist * tanH) * h / 2, minHit);
    const d = Math.hypot(sx - x, sy - y);
    if (d <= rad && d / rad < bestScore) { bestScore = d / rad; best = k; }
  }
  if (best < 0) {
    // nothing round was hit: was it the belt? where the ray meets the orbital plane
    raycaster.setFromCamera(_ndc.set(x / w * 2 - 1, -(y / h) * 2 + 1), camera);
    if (raycaster.ray.intersectPlane(PLANE, _s)) {
      const r = Math.hypot(_s.x, _s.z);
      if (Math.abs(r - BELT_R) < 4.2) { best = BELT; beltSpot.set(_s.x, 0, _s.z).setLength(BELT_R); beltPicked = true; }
      else if (r > 96 && r < 119) best = KUIPER;
    }
  }
  return best;
}
const raycaster = new THREE.Raycaster(), _ndc = new THREE.Vector2(), PLANE = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);

let downAt = null;
renderer.domElement.addEventListener('pointerdown', (e) => { downAt = { x: e.clientX, y: e.clientY, t: performance.now() }; });
renderer.domElement.addEventListener('pointerup', (e) => {
  if (!downAt || !ready) return;
  const tap = Math.hypot(e.clientX - downAt.x, e.clientY - downAt.y) < 6 && performance.now() - downAt.t < 500;
  downAt = null;
  if (!tap) return;
  if (mode === 'tour' && playing) { pause(); return; }
  const k = pick(e.clientX, e.clientY);
  if (k >= 0) select(k);
});

/* ---------------- hint ---------------- */
const hintEl = $('hint');
hintEl.textContent = COARSE ? T.hintTouch : T.hintMouse;
let hintTimer = 0;
function hideHint() { clearTimeout(hintTimer); hintEl.classList.remove('is-on'); }

/* ---------------- frame render ---------------- */
const tmp = new THREE.Vector3();
const capEl = $('cap'), titleEl = $('title'), outroEl = $('outro');
let capShot = -1;
function setCaption(j) {
  if (j === capShot) return; capShot = j;
  const sh = SHOTS[j];
  const d = sh.type === 'sun' ? SUN_INFO : sh.type === 'planet' ? PLANETS[sh.i] : null;
  if (!d) return;
  const x = txt(d);
  capEl.querySelector('.bbs__cap-idx').textContent = sh.type === 'sun' ? T.atCentre : T.planetN(sh.i + 1);
  capEl.querySelector('.bbs__cap-name').innerHTML = `${x.name}<small lang="${x.altLang}">${x.alt}</small>`;
  capEl.querySelector('ul').innerHTML = x.info.map((s) => `<li>${s}</li>`).join('');
}

const realNow = () => mode === 'explore' && !fast;
function updateWorld(t, fx) {
  sunMat.uniforms.uTime.value = fx;
  sun.rotation.y = t * 0.03;
  const real = realNow(), d = daysNow();
  for (let i = 0; i < bodies.length; i++) {
    const b = bodies[i];
    if (real) {
      const a = realLon(i, d);
      b.orbit.position.set(Math.cos(a) * b.p.a, 0, -Math.sin(a) * b.p.a);
      b.mesh.rotation.y = d / SPIN_DAYS[i] * Math.PI * 2;
    } else if (mode === 'explore') {
      const a = liveA0[i] + b.p.w * t;
      b.orbit.position.set(Math.cos(a) * b.p.a, 0, -Math.sin(a) * b.p.a);
      b.mesh.rotation.y = t * b.p.spin;
    } else {
      planetPos(i, t, b.orbit.position);
      b.mesh.rotation.y = t * b.p.spin;
    }
  }
  const earth = bodies[2];
  if (earth && earthClouds) {
    if (real) {
      // the real Earth: its true axis and turn; the clouds drift slowly over it
      earth.mesh.parent.quaternion.identity();
      earthOrientation(d, earth.mesh.quaternion);
      const drift = fx * 0.004;
      earthClouds.quaternion.copy(earth.mesh.quaternion).multiply(_ry.setFromAxisAngle(UP, drift));
      cloudShiftU.value = -drift / (Math.PI * 2);
    } else {
      earth.mesh.parent.rotation.set(0, 0, earth.p.tilt);
      earth.mesh.rotation.set(0, t * earth.p.spin, 0);
      earthClouds.rotation.set(0, t * 0.04, 0);
      cloudShiftU.value = (earth.mesh.rotation.y - earthClouds.rotation.y) / (Math.PI * 2);
    }
  }
  if (moon) {
    const a = real ? moonLon(d) : t * 1.1 + 1;
    moon.position.set(Math.cos(a) * 2.3, real ? 0 : Math.sin(a) * 0.25, -Math.sin(a) * 2.3);
    moon.rotation.y = real ? a + Math.PI : -a;   // the same face always turned to Earth
  }
  belt.rotation.y = t * 0.02;
  if (saturnRing) ringC.copy(saturnRing.position);
  updateExtras(t, real, d);
}

/* with the card open, slide the picture so the chosen body sits in the space
   beside it (or above it on a phone) instead of under it */
const viewShift = { x: 0, y: 0 };
function updateViewShift() {
  const w = renderer.domElement.clientWidth, h = renderer.domElement.clientHeight;
  let tx = 0, ty = 0;
  if (cardIdx >= 0 && !(mode === 'tour' && playing)) {
    if (w > 600) tx = Math.min(card.offsetWidth + 28, w * 0.45) / 2;
    else ty = Math.min(card.offsetHeight + 60, h * 0.6) / 2;
  }
  const k = REDUCED ? 1 : 0.12;
  viewShift.x += (tx - viewShift.x) * k; viewShift.y += (ty - viewShift.y) * k;
  if (Math.abs(tx - viewShift.x) < 0.5) viewShift.x = tx;
  if (Math.abs(ty - viewShift.y) < 0.5) viewShift.y = ty;
  if (viewShift.x || viewShift.y) camera.setViewOffset(w, h, viewShift.x, viewShift.y, w, h);
  else if (camera.view && camera.view.enabled) camera.clearViewOffset();
  return tx !== viewShift.x || ty !== viewShift.y;
}

const _lw = new THREE.Vector3();
/* a label shows when its body is worth naming from where the camera is */
function labelAlpha(k, cd) {
  const kd = kindOf(k);
  if (kd === 'sun') return 1 - smooth(900, 1400, cd);
  if (kd === 'planet' || kd === 'belt') return 1 - smooth(250, 380, cd);
  if (kd === 'region') return k === KUIPER ? smooth(190, 250, cd) * (1 - smooth(1100, 1500, cd)) : k === HELIO ? smooth(380, 470, cd) : smooth(850, 1000, cd);
  const dn = camera.position.distanceTo(bodyWorld(k, _lw));
  if (kd === 'moon') {
    const p = parentIdx(k), pr = isPlanet(p) ? PLANETS[p - 1].r : BODIES[p].r;
    return 1 - smooth(pr * 14, pr * 22, camera.position.distanceTo(bodyWorld(p, _lw)));
  }
  if (k === KEY_IDX.ceres) return 1 - smooth(18, 30, dn);
  return Math.max(smooth(kd === 'craft' ? 200 : 150, kd === 'craft' ? 260 : 200, cd) * (1 - smooth(800, 1100, cd)), 1 - smooth(10, 25, dn));
}
function labelAnchor(k, out) {
  if (k === BELT) { nearestBeltSpot(out); out.y += 1.6; return out; }
  if (k === 0) return out.set(0, SUN_R * 1.2, 0);
  if (isPlanet(k)) { out.copy(bodies[k - 1].orbit.position); out.y += PLANETS[k - 1].r * (PLANETS[k - 1].ring ? 1.4 : 1.2); return out; }
  if (k === KUIPER) { const az = Math.atan2(camera.position.z, camera.position.x); return out.set(Math.cos(az) * 112, 6, Math.sin(az) * 112); }
  if (k === HELIO) return out.copy(HELIO_NOSE).multiplyScalar(auToScene(120) + 4);
  if (k === OORT) return out.set(0, 470, 0);
  bodyWorld(k, out); out.y += (BODIES[k].r || 0.5) * 1.5; return out;
}

let labelsOn = true;
function renderFrame() {
  const touring = mode === 'tour';
  updateWorld(worldT, touring ? tourT : fxT);
  if (touring && !free) {
    cameraAt(tourT, camState);
    camera.position.copy(camState.p); camera.lookAt(camState.q);
    controls.target.copy(camState.q);
  }
  fadeExtras();
  // the near plane follows the zoom: close enough for a moon, far enough to keep depth precise out at the Oort cloud
  const nearW = clamp(camera.position.distanceTo(controls.target) * 0.004, 0.02, 6);
  if (Math.abs(camera.near - nearW) > nearW * 0.2) { camera.near = nearW; camera.updateProjectionMatrix(); }
  skyMesh.position.copy(camera.position);
  updateMoonShadows();
  sunRim.lookAt(camera.position);
  const sd = camera.position.length();
  rimMat.uniforms.uLimb.value = Math.sqrt(Math.max(0.0001, 1 - (SUN_R / Math.max(sd, SUN_R * 1.0001)) ** 2));
  composer.render();

  // overlays: title, captions and outro belong to the tour
  let tagA = labelsOn ? 1 : 0;
  if (touring) {
    const j = shotAt(tourT), sh = SHOTS[j], lt = tourT - sh.s, len = sh.e - sh.s, hide = free ? 0 : 1;
    titleEl.style.opacity = hide * smooth(0.6, 1.8, tourT) * (1 - smooth(5.2, 6.6, tourT));
    titleEl.style.transform = `scale(${1 + tourT * 0.008})`;
    outroEl.style.opacity = hide * smooth(67, 69, tourT);
    if (!free && (sh.type === 'sun' || sh.type === 'planet')) {
      setCaption(j);
      capEl.style.opacity = smooth(1.3, 2.3, lt) * (1 - smooth(len - 0.7, len - 0.1, lt));
      capEl.style.transform = `translateX(${(1 - smooth(1.3, 2.3, lt)) * -16}px)`;
    } else capEl.style.opacity = 0;
    if (!free) tagA = sh.type === 'wide0' ? smooth(1.5, 2.5, lt) * (1 - smooth(6.3, 7, lt)) : sh.type === 'wide1' ? smooth(2.5, 3.5, lt) : 0;
  }
  const w = renderer.domElement.clientWidth, h = renderer.domElement.clientHeight;
  const cd = camera.position.length();
  if (youDot) {
    youDot.visible = realNow();
    let show = false;
    if (youDot.visible && labelsOn) {
      youDot.getWorldPosition(_yw);
      bodies[2].orbit.getWorldPosition(_ye);
      const facing = _yw.clone().sub(_ye).dot(camera.position.clone().sub(_yw)) > 0;
      if (facing && camera.position.distanceTo(_yw) < 40) {
        _yw.project(camera);
        show = _yw.z < 1;
        if (show) youTag.style.transform = `translate(${((_yw.x + 1) / 2 * w).toFixed(1)}px, ${((1 - _yw.y) / 2 * h).toFixed(1)}px) translate(-50%, -150%)`;
      }
    }
    youTag.style.opacity = show ? 1 : 0;
    youTag.style.visibility = show ? 'visible' : 'hidden';
  }
  tagEls.forEach((el, k) => {
    const a = tagA * labelAlpha(k, cd);
    if (a <= 0.01) { el.style.opacity = 0; el.style.visibility = 'hidden'; return; }
    labelAnchor(k, tmp);
    tmp.project(camera);
    if (tmp.z > 1 || Math.abs(tmp.x) > 1.2 || Math.abs(tmp.y) > 1.2) { el.style.opacity = 0; el.style.visibility = 'hidden'; return; }
    el.style.visibility = 'visible';
    el.style.opacity = a;
    el.style.transform = `translate(${((tmp.x + 1) / 2 * w).toFixed(1)}px, ${((1 - tmp.y) / 2 * h).toFixed(1)}px) translate(-50%, -150%)`;
  });
}

function resize() {
  const w = root.clientWidth, h = root.clientHeight;
  if (!w || !h) return;
  renderer.setPixelRatio(dpr);
  renderer.setSize(w, h, false); camera.aspect = w / h;
  if (camera.view && camera.view.enabled) camera.setViewOffset(w, h, viewShift.x, viewShift.y, w, h);
  camera.updateProjectionMatrix();
  composer.setPixelRatio(dpr); composer.setSize(w, h);
  dirty = true;
}
new ResizeObserver(resize).observe(root);
if ('IntersectionObserver' in window) {
  new IntersectionObserver((en) => { visible = en[0].isIntersecting; if (visible) dirty = true; }).observe(root);
}

/* ---------------- player ---------------- */
const seek = $('seek'); seek.max = DUR; seek.step = 1 / FPS;
const fmt = (s) => { const m = Math.floor(s / 60), r = s - m * 60; return bn(`${String(m).padStart(2, '0')}:${r.toFixed(2).padStart(5, '0')}`); };
const ICON_PLAY = 'M7 4v16l13-8z', ICON_PAUSE = 'M6 4h4v16H6zm8 0h4v16h-4z';
const ICON_FS = 'M5 5h5v2H7v3H5zm9 0h5v5h-2V7h-3zM5 14h2v3h3v2H5zm12 0h2v5h-5v-2h3z';
const ICON_FS_EXIT = 'M8 5h2v5H5V8h3zm6 0h2v3h3v2h-5zM5 14h5v5H8v-3H5zm9 0h5v2h-3v3h-2z';
const timeRead = $('time'), frameRead = $('frame');
function updateUI() {
  const f = Math.min(TOTAL, Math.floor(tourT * FPS + 1e-6) + 1);
  timeRead.textContent = `${fmt(tourT)} / ${fmt(DUR)}`;
  frameRead.textContent = T.frame(f);
  if (!seeking) seek.value = tourT;
  seek.setAttribute('aria-valuetext', fmt(tourT));
  $('playIcon').setAttribute('d', playing ? ICON_PAUSE : ICON_PLAY);
  $('play').setAttribute('aria-label', playing ? T.pause : T.play);
  $('loop').setAttribute('aria-pressed', loop ? 'true' : 'false');
  root.classList.toggle('is-tour', mode === 'tour');
  root.classList.toggle('is-explore', mode === 'explore');
  root.classList.toggle('is-playing', mode === 'tour' && playing);
  root.classList.toggle('is-free', mode === 'tour' && free);
  $('bigplay').hidden = !(mode === 'tour' && !playing && !free && ready);
  $('camreset').hidden = !(mode === 'tour' && free);
  updateNow();
  syncControls();
}

function play() {
  if (tourT >= DUR - 1e-6) tourT = 0;
  free = false; fly = null; closeCard();
  playing = true; updateUI(); poke(); dirty = true;
}
function pause() { playing = false; updateUI(); poke(); dirty = true; }
const toggle = () => (playing ? pause() : play());
function endFreeLook() { if (free) { free = false; fly = null; closeCard(); dirty = true; updateUI(); } }
function setTime(x) { tourT = clamp(x, 0, DUR); endFreeLook(); dirty = true; updateUI(); }
function step(n) { pause(); setTime(Math.round(tourT * FPS + n) / FPS); }

function startTour() {
  if (!ready) return;
  mode = 'tour'; fly = null; free = false; focus = -1; closeCard(); hideHint();
  tourT = 0; capShot = -1;
  play();
}
function exitTour() {
  pause();
  mode = 'explore';
  // the planets carry on from exactly where the tour left them
  worldT = tourT;
  if (free) {
    // the viewer had already taken the camera: keep whatever they were looking at
    if (!fly) focusPoint(focus, lastFocus);
  } else {
    fly = null;
    const sh = SHOTS[shotAt(tourT)];
    focus = sh.type === 'planet' ? sh.i + 1 : sh.type === 'sun' ? 0 : -1;
    focusPoint(focus, lastFocus);
    setLimits(focus);
    syncHome();
  }
  free = false;
  titleEl.style.opacity = 0; outroEl.style.opacity = 0; capEl.style.opacity = 0;
  clearTimeout(idleTimer);
  root.classList.remove('bar-hidden', 'hide-cursor');
  updateUI();
}

$('tour').addEventListener('click', startTour);
$('exit').addEventListener('click', exitTour);
$('play').addEventListener('click', toggle);
$('bigplay').addEventListener('click', toggle);
$('restart').addEventListener('click', () => setTime(0));
$('prev').addEventListener('click', () => step(-1));
$('next').addEventListener('click', () => step(1));
$('loop').addEventListener('click', () => { loop = !loop; updateUI(); });
$('speed').addEventListener('change', (e) => { speed = +e.target.value; });
$('camreset').addEventListener('click', endFreeLook);
$('home').addEventListener('click', goHome);
root.querySelectorAll('[data-s="fs"]').forEach((b) => b.addEventListener('click', toggleFs));
$('orbits').addEventListener('click', () => {
  hideFastTip();
  fast = !fast;
  // sped-up motion picks up from where the planets really are
  if (fast) { const d = daysNow(); PLANETS.forEach((p, i) => { liveA0[i] = realLon(i, d) - p.w * worldT; }); }
  syncOrbitsButton();
  updateNow();
  dirty = true;
});
function syncOrbitsButton() {
  const b = $('orbits');
  b.setAttribute('aria-pressed', fast ? 'true' : 'false');
  b.title = fast ? b.dataset.on : b.dataset.off;
  b.setAttribute('aria-label', b.title);
}
$('labels').addEventListener('click', () => {
  labelsOn = !labelsOn;
  const b = $('labels');
  b.setAttribute('aria-pressed', labelsOn ? 'true' : 'false');
  b.title = labelsOn ? b.dataset.on : b.dataset.off;
  b.setAttribute('aria-label', b.title);
  dirty = true;
});
seek.addEventListener('pointerdown', () => { seeking = true; });
seek.addEventListener('input', () => { setTime(+seek.value); });
const endSeek = () => { if (seeking) { seeking = false; updateUI(); } };
seek.addEventListener('pointerup', endSeek); seek.addEventListener('change', endSeek);
// a mouse click leaves focus on the button, and Space would then press it again
root.querySelectorAll('.bbs-bar button').forEach((b) => b.addEventListener('pointerup', (e) => { if (e.pointerType === 'mouse') b.blur(); }));

/* ---------------- sound effects (explore mode only; the tour stays silent) ----------------
   All made in code with the Web Audio API: a low drone of space that deepens as the camera pulls
   back, a whoosh for each flight, a soft chime for a card and the Sun's rumble up close. Off until
   the reader turns it on (a small note offers it on a first visit); the choice is remembered, and the
   sound goes quiet when the scene is off screen. */
const sfx = { on: false, ctx: null };
let sfxChosen = false;
try { const v = localStorage.getItem('bb_solar_sfx'); sfxChosen = v !== null; sfx.on = v === '1'; } catch (e) { /* storage blocked */ }
function sfxStart() {
  if (!sfx.on) return;
  if (sfx.ctx) { if (sfx.ctx.state === 'suspended') sfx.ctx.resume(); return; }
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return;
  const c = sfx.ctx = new AC();
  const master = sfx.master = c.createGain();
  master.gain.setValueAtTime(0, c.currentTime);
  master.gain.setTargetAtTime(0.9, c.currentTime, 1.2);
  master.connect(c.destination);
  // brown noise: the raw material for wind, whooshes and the Sun
  const nb = c.createBuffer(1, c.sampleRate * 3, c.sampleRate), nd = nb.getChannelData(0);
  let last = 0;
  for (let i = 0; i < nd.length; i++) { last = (last + 0.02 * (Math.random() * 2 - 1)) / 1.02; nd[i] = last * 3.5; }
  sfx.noise = nb;
  const lp = sfx.ambLP = c.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 420; lp.Q.value = 0.6;
  const amb = sfx.amb = c.createGain(); amb.gain.value = 0.05;
  lp.connect(amb); amb.connect(master);
  [[55, 0.5, 'sine'], [55.4, 0.5, 'sine'], [82.4, 0.22, 'triangle'], [110.2, 0.12, 'triangle'], [164.8, 0.06, 'triangle']].forEach(([f, v, ty]) => {
    const o = c.createOscillator(); o.type = ty; o.frequency.value = f;
    const g = c.createGain(); g.gain.value = v; o.connect(g); g.connect(lp); o.start();
  });
  const lfo = c.createOscillator(); lfo.frequency.value = 0.05;
  const lg = c.createGain(); lg.gain.value = 140; lfo.connect(lg); lg.connect(lp.frequency); lfo.start();
  const wind = c.createBufferSource(); wind.buffer = nb; wind.loop = true;
  const bp = c.createBiquadFilter(); bp.type = 'bandpass'; bp.frequency.value = 600; bp.Q.value = 0.7;
  const wg = c.createGain(); wg.gain.value = 0.012; wind.connect(bp); bp.connect(wg); wg.connect(master); wind.start();
  const rum = c.createBufferSource(); rum.buffer = nb; rum.loop = true;
  const rlp = c.createBiquadFilter(); rlp.type = 'lowpass'; rlp.frequency.value = 140;
  const rg = sfx.rum = c.createGain(); rg.gain.value = 0; rum.connect(rlp); rlp.connect(rg); rg.connect(master); rum.start();
}
function sfxLive() { return sfx.on && sfx.ctx && sfx.ctx.state === 'running' && mode === 'explore'; }
function sfxWhoosh(dur) {
  if (!sfxLive() || dur < 0.3) return;
  const c = sfx.ctx, t = c.currentTime;
  const src = c.createBufferSource(); src.buffer = sfx.noise; src.loop = true;
  const bp = c.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = 1.2;
  bp.frequency.setValueAtTime(220, t); bp.frequency.exponentialRampToValueAtTime(1400, t + dur * 0.5); bp.frequency.exponentialRampToValueAtTime(260, t + dur);
  const g = c.createGain(); g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(0.22, t + dur * 0.45); g.gain.linearRampToValueAtTime(0, t + dur);
  src.connect(bp); bp.connect(g);
  if (c.createStereoPanner) {
    const pan = c.createStereoPanner(); pan.pan.setValueAtTime(-0.6, t); pan.pan.linearRampToValueAtTime(0.6, t + dur);
    g.connect(pan); pan.connect(sfx.master);
  } else g.connect(sfx.master);
  src.start(t, Math.random() * 2); src.stop(t + dur + 0.1);
}
function sfxChime() {
  if (!sfxLive()) return;
  const c = sfx.ctx, t = c.currentTime + 0.02;
  [[660, 0.045], [990, 0.025], [1320, 0.012]].forEach(([f, v], k) => {
    const o = c.createOscillator(); o.type = 'sine'; o.frequency.value = f;
    const g = c.createGain(); g.gain.setValueAtTime(0, t + k * 0.04); g.gain.linearRampToValueAtTime(v, t + k * 0.04 + 0.01); g.gain.exponentialRampToValueAtTime(0.0001, t + k * 0.04 + 1.6);
    o.connect(g); g.connect(sfx.master); o.start(t + k * 0.04); o.stop(t + k * 0.04 + 1.7);
  });
}
function sfxUpdate() {
  if (!sfx.ctx) return;
  const want = sfx.on && visible && !document.hidden && mode === 'explore';
  if (!want) { if (sfx.ctx.state === 'running') sfx.ctx.suspend(); return; }
  if (sfx.ctx.state === 'suspended') sfx.ctx.resume();
  const ds = camera.position.length(), t = sfx.ctx.currentTime;
  sfx.rum.gain.setTargetAtTime(0.4 * (1 - smooth(14, 70, ds)), t, 0.3);
  sfx.amb.gain.setTargetAtTime(0.05 * (0.7 + 0.5 * smooth(150, 900, ds)), t, 0.6);
  sfx.ambLP.frequency.setTargetAtTime(420 - 180 * smooth(150, 900, ds), t, 0.6);
}
function syncSoundButton() {
  const b = $('sound');
  b.setAttribute('aria-pressed', sfx.on ? 'true' : 'false');
  b.title = sfx.on ? b.dataset.on : b.dataset.off;
  b.setAttribute('aria-label', b.title);
}
const soundTip = $('soundtip');
let tipTimer = 0;
function hideSoundTip() {
  clearTimeout(tipTimer);
  if (soundTip.hidden) return;
  soundTip.classList.remove('is-on');
  setTimeout(() => { soundTip.hidden = true; }, 400);
}
function showSoundTip() {
  if (sfxChosen || sfx.on || !(window.AudioContext || window.webkitAudioContext)) return;
  soundTip.hidden = false;
  requestAnimationFrame(() => soundTip.classList.add('is-on'));
  tipTimer = setTimeout(hideSoundTip, 12000);
}
function setSound(on) {
  sfx.on = on; sfxChosen = true;
  try { localStorage.setItem('bb_solar_sfx', on ? '1' : '0'); } catch (e) { /* storage blocked */ }
  if (on) sfxStart(); else if (sfx.ctx) sfx.ctx.suspend();
  syncSoundButton();
  hideSoundTip();
}
$('sound').addEventListener('click', () => setSound(!sfx.on));
soundTip.addEventListener('click', () => setSound(true));
syncSoundButton();
// a reader who turned sound on before: it starts with the first touch, as browsers require
root.addEventListener('pointerdown', sfxStart);

/* ---------------- fullscreen (with a stand-in for phones that have none) ---------------- */
let fsPending = false;
const fsElement = () => document.fullscreenElement || document.webkitFullscreenElement;
function setPseudoFs(on) {
  root.classList.toggle('is-pseudo-fs', on);
  document.body.classList.toggle('bb-noscroll', on);
  syncFsIcons();
}
function toggleFs() {
  if (fsElement()) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); return; }
  if (root.classList.contains('is-pseudo-fs')) { setPseudoFs(false); return; }
  const req = root.requestFullscreen || root.webkitRequestFullscreen;
  if (!req) { setPseudoFs(true); return; }
  try {
    const p = req.call(root);
    if (p && p.catch) p.catch(() => setPseudoFs(true));
  } catch (e) { setPseudoFs(true); }
  // some in-app browsers neither grant nor refuse: fill the window instead
  fsPending = true;
  setTimeout(() => { if (fsPending && !fsElement()) setPseudoFs(true); fsPending = false; }, 1200);
}
function syncFsIcons() {
  fsPending = false;
  if (fsElement() && root.classList.contains('is-pseudo-fs')) { root.classList.remove('is-pseudo-fs'); document.body.classList.remove('bb-noscroll'); }
  const on = !!fsElement() || root.classList.contains('is-pseudo-fs');
  root.classList.toggle('is-fs', on);
  root.querySelectorAll('[data-s="fs"]').forEach((b) => {
    b.querySelector('path').setAttribute('d', on ? ICON_FS_EXIT : ICON_FS);
    b.setAttribute('aria-label', on ? b.dataset.off : b.dataset.on);
    b.title = b.getAttribute('aria-label');
  });
}
document.addEventListener('fullscreenchange', syncFsIcons);
document.addEventListener('webkitfullscreenchange', syncFsIcons);

/* ---------------- keyboard ---------------- */
addEventListener('keydown', (e) => {
  if (e.ctrlKey || e.metaKey || e.altKey || !ready) return;
  const tg = e.target, tag = tg && tg.tagName;
  if (tag === 'TEXTAREA' || tag === 'SELECT' || (tg && tg.isContentEditable)) return;
  if (tag === 'INPUT' && !/^(range|button|checkbox)$/.test(tg.type)) return;
  const k = e.key;
  if (k === 'Escape') {
    if (cardIdx >= 0) closeCard();
    else if (root.classList.contains('is-pseudo-fs')) setPseudoFs(false);
    return;
  }
  if (mode !== 'tour') return;
  // let a focused control do its own thing with the keys it owns
  if ((k === ' ' || k === 'Enter') && (tag === 'BUTTON' || tag === 'A')) return;
  if (tag === 'INPUT' && /^(Arrow|Home|End|Page)/.test(k)) return;
  const map = {
    ' ': toggle, k: toggle, K: toggle,
    ArrowLeft: () => setTime(tourT - 1), ArrowRight: () => setTime(tourT + 1),
    ',': () => step(-1), '.': () => step(1), '<': () => step(-1), '>': () => step(1),
    Home: () => setTime(0), End: () => setTime(DUR),
    l: () => $('loop').click(), L: () => $('loop').click(),
    f: toggleFs, F: toggleFs,
    c: endFreeLook, C: endFreeLook,
  };
  if (map[k]) { e.preventDefault(); map[k](); poke(); }
});

// auto-hide the control bar while the tour plays
let idleTimer;
function poke() {
  root.classList.remove('bar-hidden', 'hide-cursor');
  clearTimeout(idleTimer);
  if (mode !== 'tour') return;
  idleTimer = setTimeout(() => { if (mode === 'tour' && playing && !seeking) root.classList.add('bar-hidden', 'hide-cursor'); }, 2600);
}
root.addEventListener('pointermove', poke);
root.addEventListener('focusin', poke);

/* ---------------- loop ---------------- */
let lastPerf = performance.now(), slow = 0, frames = 0, hiTimer = 0;
function tick(now) {
  requestAnimationFrame(tick);
  const dt = Math.min(0.1, (now - lastPerf) / 1000); lastPerf = now;
  if (!ready) return;
  if (mode === 'tour') {
    if (playing && !seeking) {
      tourT += dt * speed;
      if (tourT >= DUR) {
        if (loop) tourT = 0; else { tourT = DUR; pause(); }
      }
      dirty = true; updateUI();
    }
    worldT = tourT;
  } else {
    if (fast) worldT += dt;
    fxT += dt;
    dirty = true;
  }
  if (frames % 30 === 0) updateNow();
  if (!visible || document.hidden) return;
  sfxUpdate();
  hiTimer += dt;
  if (hiTimer > 0.5) { hiTimer = 0; checkHi(); }
  if (fly) { if (mode === 'explore') updateWorld(worldT, fxT); stepFly(dt); dirty = true; }
  else if (mode === 'explore') { updateWorld(worldT, fxT); follow(); }
  if (controls.enabled && controls.update()) dirty = true;
  if (updateViewShift()) dirty = true;
  if (!dirty) return;
  renderFrame();
  dirty = false;

  // a phone that cannot keep up gets fewer pixels rather than a stutter
  frames++;
  slow = dt > 1 / 40 ? slow + 1 : Math.max(0, slow - 2);
  if (slow > 45 && dpr > Q.minDpr && frames > 60) { dpr = Math.max(Q.minDpr, dpr - 0.25); slow = 0; resize(); }
}

/* ---------------- start ---------------- */
// for testing in the browser console
window.__bbSolar = {
  get mode() { return mode; }, get t() { return tourT; }, get worldT() { return worldT; }, get playing() { return playing; }, get speed() { return speed; },
  get loop() { return loop; }, get free() { return free; }, get focus() { return focus; }, get fast() { return fast; }, get sfx() { return { on: sfx.on, state: sfx.ctx ? sfx.ctx.state : null }; },
  get flying() { return !!fly; }, get card() { return cardIdx; }, get dpr() { return dpr; },
  camera, controls, scene, pick, select, setTime, render: () => renderFrame(), get clouds() { return earthClouds; }, cloudShift: cloudShiftU, cloudTex: cloudTexU, renderer,
};

const progressEl = $('progress'), loadText = $('loadtext');
const breathe = () => new Promise((r) => { let done = false; const go = () => { if (!done) { done = true; r(); } }; requestAnimationFrame(() => setTimeout(go, 0)); setTimeout(go, 60); });
function progress(x) {
  progressEl.style.transform = `scaleX(${x})`;
  loadText.textContent = T.loading(Math.round(x * 100));
}

(async () => {
  try {
    progress(0.04);
    // the photographs start downloading at once, while the planets are painted at low resolution
    const jobs = {};
    for (const key of Object.keys(REAL)) {
      jobs[key] = fetchBody(key);
      jobs[key].catch(() => {}); // handled below, once the planets exist
    }
    await breathe();
    for (let n = 0; n < PLANETS.length; n++) {
      buildPlanet(PLANETS[n]);
      progress(0.04 + 0.26 * (n + 1) / PLANETS.length);
      await breathe();
    }
    buildExtras();
    buildYou();
    // each photograph replaces its painting as it arrives; one that fails gets a full-size painting
    for (const b of bodies) jobs[b.p.key].then((tx) => applyReal(b, tx), () => paintBody(b, Q.tex));
    fetchTex('sky', true, { noMips: true }).then((t) => { skyMat.uniforms.uSky.value = t; skyMesh.visible = true; dirty = true; }, () => {});
    jobs.moon.then(applyMoon, () => paintMoon(Q.moon));
    // wait for them, but not for ever: on a slow line the paintings show first
    const waitFrom = performance.now();
    while (texDone < texTotal && performance.now() - waitFrom < 5000) {
      progress(0.3 + 0.66 * texDone / texTotal);
      await new Promise((r) => setTimeout(r, 80));
    }
    await breathe();
    resize();
    updateWorld(0, 0);
    // start far out and glide in to the whole system
    camera.position.set(330, 210, 260).multiplyScalar(fit());
    controls.target.copy(OVERVIEW_Q);
    camera.lookAt(controls.target);
    renderer.compile(scene, camera);
    progress(1);
    ready = true;
    root.classList.add('is-ready');
    $('loading').hidden = true;
    flyTo(-1, { dur: 2.8 });
    renderFrame();
    updateUI();
    setTimeout(loadMoonTextures, 1200);
    hintEl.classList.add('is-on');
    setTimeout(showNowTip, 600);
    setTimeout(showSoundTip, 1500);
    hintTimer = setTimeout(hideHint, 9000);
  } catch (err) {
    fail();
    if (window.console) console.error(err);
  }
  requestAnimationFrame(tick);
})();
}
