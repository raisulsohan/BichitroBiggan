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
  showInfo: ' — show facts', centre: 'The centre of the Solar System', between: 'Between Mars and Jupiter', atCentre: 'At the centre',
  planetOf: (i) => `Planet ${i} / 8 · ${['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth'][i - 1]} from the Sun`,
  planetN: (i) => `Planet ${i} / 8`, closeCard: 'Close the facts', play: 'Play', pause: 'Pause',
  frame: (f) => `Frame ${f} / ${TOTAL}`, loading: (p) => `Building the 3D Solar System… ${p}%`,
} : {
  hintTouch: 'আঙুলে টেনে ঘোরান · দুই আঙুলে জুম · গ্রহে ট্যাপ করুন', hintMouse: 'মাউস টেনে ঘোরান · স্ক্রল করে জুম · গ্রহে ক্লিক করুন',
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
const camera = new THREE.PerspectiveCamera(45, 1, 0.05, 4000);

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
      for (const k of ['map', 'bumpMap', 'roughnessMap', 'emissiveMap']) if (m[k]) m[k].needsUpdate = true;
      if (m.uniforms) for (const u of Object.values(m.uniforms)) if (u.value && u.value.isTexture) u.value.needsUpdate = true;
    }
  });
  $('glmsg').hidden = true; dirty = true;
});

scene.add(new THREE.AmbientLight(0xffffff, 0.07));
scene.add(new THREE.PointLight(0xfff1dd, 2.8, 0, 0));

// stars
{
  const n = Q.stars, pos = new Float32Array(n * 3), col = new Float32Array(n * 3);
  for (let i = 0; i < n; i++) {
    const u = rng() * 2 - 1, th = rng() * Math.PI * 2, s = Math.sqrt(1 - u * u), R = 1500 + rng() * 600;
    pos.set([R * s * Math.cos(th), R * u, R * s * Math.sin(th)], i * 3);
    const tint = rng(), b = 0.45 + rng() * 0.55;
    const c = tint < 0.15 ? [1, 0.8, 0.6] : tint < 0.3 ? [0.7, 0.8, 1] : [1, 1, 1];
    col.set(c.map((v) => v * b), i * 3);
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setAttribute('color', new THREE.BufferAttribute(col, 3));
  scene.add(new THREE.Points(g, new THREE.PointsMaterial({ size: 1.7, sizeAttenuation: false, vertexColors: true, depthWrite: false })));
}

// sun
const sunMat = new THREE.ShaderMaterial({
  uniforms: { uTime: { value: 0 } },
  vertexShader: `varying vec3 vP; varying vec3 vN; varying vec3 vV;
    void main(){ vP = position; vec4 wp = modelMatrix*vec4(position,1.); vN = normalize(mat3(modelMatrix)*normal); vV = normalize(cameraPosition-wp.xyz); gl_Position = projectionMatrix*viewMatrix*wp; }`,
  fragmentShader: `precision highp float;
    uniform float uTime; varying vec3 vP; varying vec3 vN; varying vec3 vV;
    float hash(vec3 p){ p = fract(p*0.3183099+.1); p *= 17.0; return fract(p.x*p.y*p.z*(p.x+p.y+p.z)); }
    float vnoise(vec3 x){ vec3 i=floor(x), f=fract(x); f=f*f*(3.-2.*f);
      return mix(mix(mix(hash(i),hash(i+vec3(1,0,0)),f.x), mix(hash(i+vec3(0,1,0)),hash(i+vec3(1,1,0)),f.x),f.y),
                 mix(mix(hash(i+vec3(0,0,1)),hash(i+vec3(1,0,1)),f.x), mix(hash(i+vec3(0,1,1)),hash(i+vec3(1,1,1)),f.x),f.y), f.z); }
    float fbm(vec3 p){ float s=0., a=.5; for(int i=0;i<5;i++){ s+=a*vnoise(p); p*=2.03; a*=.5; } return s; }
    void main(){
      vec3 p = normalize(vP)*2.6;
      float n = fbm(p + vec3(uTime*0.06, 0., uTime*0.04));
      float n2 = fbm(p*2.4 - vec3(0., uTime*0.09, 0.) + n*1.5);
      float c = clamp(n*0.75 + n2*0.55 - 0.15, 0., 1.);
      vec3 col = mix(vec3(0.95,0.28,0.03), vec3(1.0,0.72,0.22), c);
      col = mix(col, vec3(1.0,0.97,0.78), pow(c, 2.6));
      float mu = max(dot(vN, vV), 0.);
      col *= 0.62 + 0.55*pow(mu, 0.5);
      gl_FragColor = vec4(pow(col, vec3(2.2)) * 1.5, 1.);  // linear HDR: only the hottest cells cross the bloom threshold
    }`,
});
const sun = new THREE.Mesh(new THREE.SphereGeometry(SUN_R, Q.sunSeg[0], Q.sunSeg[1]), sunMat);
scene.add(sun);
function glowTex(stops) {
  const cv = document.createElement('canvas'); cv.width = cv.height = 256; const g = cv.getContext('2d');
  const gr = g.createRadialGradient(128, 128, 0, 128, 128, 128); stops.forEach(([o, c]) => gr.addColorStop(o, c));
  g.fillStyle = gr; g.fillRect(0, 0, 256, 256); const t = new THREE.CanvasTexture(cv); t.colorSpace = THREE.SRGBColorSpace; return t;
}
const glow1 = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex([[0, 'rgba(255,230,170,1)'], [0.22, 'rgba(255,190,90,0.85)'], [0.45, 'rgba(255,120,30,0.25)'], [1, 'rgba(255,90,0,0)']]), blending: THREE.AdditiveBlending, depthWrite: false, transparent: true, opacity: 0.45 }));
glow1.scale.setScalar(SUN_R * 4.2); scene.add(glow1);
const glow2 = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex([[0, 'rgba(255,200,120,0.5)'], [0.3, 'rgba(255,140,50,0.12)'], [1, 'rgba(255,100,0,0)']]), blending: THREE.AdditiveBlending, depthWrite: false, transparent: true, opacity: 0.3 }));
glow2.scale.setScalar(SUN_R * 12); scene.add(glow2);

// atmosphere rim shader
function atmosphere(radius, color, strength = 1) {
  const m = new THREE.ShaderMaterial({
    uniforms: { uColor: { value: new THREE.Color(...color) }, uS: { value: strength } },
    vertexShader: `varying vec3 vN; varying vec3 vV; varying vec3 vW;
      void main(){ vec4 wp = modelMatrix*vec4(position,1.); vW = wp.xyz; vN = normalize(mat3(modelMatrix)*normal); vV = normalize(cameraPosition-wp.xyz); gl_Position = projectionMatrix*viewMatrix*wp; }`,
    fragmentShader: `uniform vec3 uColor; uniform float uS; varying vec3 vN; varying vec3 vV; varying vec3 vW;
      void main(){ float rim = pow(1.0 - max(dot(vN, vV), 0.), 4.0);
        float lit = clamp(dot(vN, normalize(-vW))*0.8+0.35, 0., 1.);
        gl_FragColor = vec4(uColor*rim*lit*uS*0.6, 1.); }`,
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

/* city lights only on the night side: mask the emissive term by the angle to the sun (at the origin) */
function nightSideEmissive(mat) {
  mat.onBeforeCompile = (sh) => {
    sh.fragmentShader = sh.fragmentShader.replace('#include <emissivemap_fragment>', `#include <emissivemap_fragment>
      vec3 sunView = (viewMatrix * vec4(0., 0., 0., 1.)).xyz;
      totalEmissiveRadiance *= smoothstep(0.1, -0.25, dot(normal, normalize(sunView + vViewPosition)));`);
  };
}

/* Saturn: ring shadow on the planet (march toward the sun, hit the ring plane, read ring opacity) */
const ringC = new THREE.Vector3(), ringN = new THREE.Vector3();
function ringShadowOnPlanet(mat, ringTex, inner, outer) {
  mat.onBeforeCompile = (sh) => {
    Object.assign(sh.uniforms, { uRingC: { value: ringC }, uRingN: { value: ringN }, uRingTex: { value: ringTex }, uRingIn: { value: inner }, uRingOut: { value: outer } });
    sh.vertexShader = sh.vertexShader.replace('#include <common>', '#include <common>\nvarying vec3 vWP;')
      .replace('#include <worldpos_vertex>', '#include <worldpos_vertex>\nvWP = (modelMatrix * vec4(transformed, 1.0)).xyz;');
    sh.fragmentShader = sh.fragmentShader.replace('#include <common>', `#include <common>
      varying vec3 vWP; uniform vec3 uRingC; uniform vec3 uRingN; uniform sampler2D uRingTex; uniform float uRingIn; uniform float uRingOut;`)
      .replace('#include <opaque_fragment>', `{
        vec3 L = normalize(-vWP); float dn = dot(L, uRingN);
        float s = dot(uRingC - vWP, uRingN) / (abs(dn) < 1e-4 ? 1e-4 : dn);
        float r = length(vWP + L * s - uRingC);
        float u = (r - uRingIn) / (uRingOut - uRingIn);
        float a = texture2D(uRingTex, vec2(clamp(u, 0., 1.), .5)).a * step(0., s) * step(0., u) * step(u, 1.);
        outgoingLight *= 1. - a * 0.8;
      }
      #include <opaque_fragment>`);
  };
}

/* Saturn's rings: planet shadow across the rings, and dimmer when seen from the unlit face */
function ringMaterial(tex, radius) {
  return new THREE.ShaderMaterial({
    uniforms: { uTex: { value: tex }, uC: { value: ringC }, uN: { value: ringN }, uR: { value: radius }, uTint: { value: new THREE.Color(0xd8d0c0) } },
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

const bodies = [];
let moon, earthClouds, saturnRing;
function buildPlanet(p) {
  const TW = Q.tex, TH = TW / 2;
  const orbit = new THREE.Group(); // positioned on orbit
  const tilt = new THREE.Group(); tilt.rotation.z = p.tilt; orbit.add(tilt);
  const maps = paintMaps(TW, TH, PAINTERS[p.key]);
  const mat = new THREE.MeshStandardMaterial({ map: maps.map, roughness: 1, metalness: 0 });
  if (maps.bumpMap) { mat.bumpMap = maps.bumpMap; mat.bumpScale = p.key === 'earth' ? 1.2 : 2.5; }
  if (maps.roughnessMap) mat.roughnessMap = maps.roughnessMap;
  if (maps.emissiveMap) { mat.emissiveMap = maps.emissiveMap; mat.emissive.set(0xffffff); mat.emissiveIntensity = 1.4; nightSideEmissive(mat); }
  const mesh = new THREE.Mesh(new THREE.SphereGeometry(p.r, Q.seg[0], Q.seg[1]), mat);
  tilt.add(mesh);
  if (p.atmo) orbit.add(atmosphere(p.r * 1.05, p.atmo, p.key === 'earth' ? 1.4 : 0.9));
  if (p.key === 'earth') {
    earthClouds = new THREE.Mesh(new THREE.SphereGeometry(p.r * 1.012, Q.seg[0], Q.seg[1]), new THREE.MeshStandardMaterial({ map: paint(TW, TH, PAINTERS.clouds), transparent: true, depthWrite: false, roughness: 1 }));
    tilt.add(earthClouds);
    const mm = paintMaps(Q.moon, Q.moon / 2, PAINTERS.moon);
    moon = new THREE.Mesh(new THREE.SphereGeometry(0.27, 48, 32), new THREE.MeshStandardMaterial({ map: mm.map, bumpMap: mm.bumpMap, bumpScale: 2.5, roughness: 1 }));
    orbit.add(moon);
  }
  if (p.ring) {
    const inner = p.r * 1.25, outer = p.r * 2.35, RW = Q.ring;
    const geo = new THREE.RingGeometry(inner, outer, 160, 1);
    const pos = geo.attributes.position, uv = geo.attributes.uv, v = new THREE.Vector3();
    for (let i = 0; i < pos.count; i++) { v.fromBufferAttribute(pos, i); uv.setXY(i, (v.length() - inner) / (outer - inner), 0.5); }
    const cv = document.createElement('canvas'); cv.width = RW; cv.height = 4; const g = cv.getContext('2d');
    for (let x = 0; x < RW; x++) {
      const u = x / RW;
      let a = 0.55 + 0.25 * Math.sin(u * 90) * Math.sin(u * 23) + 0.2 * noise(u * 40, 0.5, 0.5);
      if (u > 0.58 && u < 0.64) a *= 0.08; // Cassini division
      if (u < 0.12) a *= u / 0.12 * 0.6;
      a *= smooth(1.0, 0.92, u);
      const b = 200 + 40 * Math.sin(u * 37);
      g.fillStyle = `rgba(${b | 0},${(b * 0.88) | 0},${(b * 0.7) | 0},${clamp(a, 0, 1)})`; g.fillRect(x, 0, 1, 4);
    }
    const tex = new THREE.CanvasTexture(cv); tex.colorSpace = THREE.SRGBColorSpace;
    const ring = new THREE.Mesh(geo, ringMaterial(tex, p.r));
    ring.rotation.x = -Math.PI / 2; tilt.add(ring);
    ringShadowOnPlanet(mat, tex, inner, outer);
    ringN.set(-Math.sin(p.tilt), Math.cos(p.tilt), 0); // ring plane normal: +Y rotated by the axial tilt
    saturnRing = orbit;
  }
  scene.add(orbit);
  bodies.push({ p, orbit, mesh });
}
const planetPos = (i, t, out = new THREE.Vector3()) => { const p = PLANETS[i], a = p.a0 + p.w * t; return out.set(Math.cos(a) * p.a, 0, -Math.sin(a) * p.a); };

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
let orbitsOn = true;
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
  if (idx === 0) return out.set(0, 0, 0);
  if (idx === BELT) return out.copy(beltSpot);
  return out.copy(bodies[idx - 1].orbit.position);
}
function focusOffset(idx, L, out) {
  const az = Math.atan2(camera.position.z - controls.target.z, camera.position.x - controls.target.x);
  if (idx < 0) return out.set(Math.cos(az) * 140, 95, Math.sin(az) * 140).multiplyScalar(fit());
  if (idx === 0) return out.set(Math.cos(az), 0.28, Math.sin(az)).normalize().multiplyScalar(27 * fit());
  if (idx === BELT) {
    // a little inside the ring and above it, so the rocks are lit and the Sun sits off to one side
    _dir.copy(L).normalize(); _side.set(-_dir.z, 0, _dir.x);
    return out.copy(_dir).multiplyScalar(-7).addScaledVector(_side, 14).addScaledVector(UP, 7.5).multiplyScalar(fit());
  }
  const pl = PLANETS[idx - 1];
  return planetView(pl, L, out).multiplyScalar(viewDist(pl) * fit());
}
function setLimits(idx) {
  controls.minDistance = idx < 0 ? 4 : idx === 0 ? SUN_R * 1.35 : idx === BELT ? 1.5 : PLANETS[idx - 1].r * (PLANETS[idx - 1].ring ? 1.6 : 1.45);
  controls.maxDistance = 600;
}
function flyTo(idx, opts = {}) {
  flushControls();
  // a click on the belt flies to where it landed; a chip, to the stretch nearest the camera
  if (idx === BELT && !beltPicked) nearestBeltSpot(beltSpot);
  beltPicked = false;
  const L = focusPoint(idx, _L);
  const off = focusOffset(idx, L, new THREE.Vector3());
  const fromP = opts.from ? opts.from.clone() : camera.position.clone();
  _goal.copy(L).add(off);
  const dur = REDUCED ? 0.01 : (opts.dur || clamp(1 + fromP.distanceTo(_goal) / 140, 1.2, 2.6));
  fly = { idx, t: 0, dur, fromP, fromQ: controls.target.clone(), off };
  focus = idx;
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
  if (!isPlanet(focus) || fly) return;
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

function openCard(idx) {
  const pos = ORDER.indexOf(idx), pi = ORDER[(pos + ORDER.length - 1) % ORDER.length], ni = ORDER[(pos + 1) % ORDER.length];
  const d = BODIES[idx], x = txt(d), prev = txt(BODIES[pi]), next = txt(BODIES[ni]);
  const kicker = idx === 0 ? T.centre : idx === BELT ? T.between : T.planetOf(idx);
  card.innerHTML =
    `<button type="button" class="bbs-card__close" data-c="close" aria-label="${T.closeCard}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6z"/></svg></button>` +
    `<p class="bbs-card__kicker">${kicker}</p>` +
    `<h2 class="bbs-card__name" id="bbs-card-title"><span class="bbs-card__dot" style="background:${d.color}" aria-hidden="true"></span>${x.name} <span class="bbs-card__en" lang="${x.altLang}">${x.alt}</span></h2>` +
    `<p class="bbs-card__note">${x.note}</p>` +
    '<dl class="bbs-card__facts">' + x.facts.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('') + '</dl>' +
    '<div class="bbs-card__nav">' +
    `<button type="button" data-c="prev" data-i="${pi}"><span aria-hidden="true">‹</span> ${prev.name}</button>` +
    `<button type="button" data-c="next" data-i="${ni}">${next.name} <span aria-hidden="true">›</span></button>` +
    '</div>';
  card.hidden = false;
  card.scrollTop = 0;
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
function goHome() {
  closeCard();
  if (mode === 'tour') { free = true; updateUI(); }
  flyTo(-1);
}

/* screen-space picking: generous on touch, so a tiny Mercury can still be tapped */
const _s = new THREE.Vector3();
function pick(cx, cy) {
  const rect = renderer.domElement.getBoundingClientRect();
  const x = cx - rect.left, y = cy - rect.top, h = rect.height, w = rect.width;
  const tanH = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
  const minHit = COARSE ? 28 : 16;
  let best = -1, bestScore = Infinity;
  for (let k = 0; k <= 8; k++) {
    const R = k === 0 ? SUN_R : PLANETS[k - 1].r * (PLANETS[k - 1].ring ? 1.9 : 1.15);
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

function updateWorld(t, fx) {
  sunMat.uniforms.uTime.value = fx;
  sun.rotation.y = t * 0.03;
  for (let i = 0; i < bodies.length; i++) {
    const b = bodies[i];
    planetPos(i, t, b.orbit.position);
    b.mesh.rotation.y = t * b.p.spin;
  }
  if (earthClouds) earthClouds.rotation.y = t * 0.04;
  if (moon) { const a = t * 1.1 + 1; moon.position.set(Math.cos(a) * 2.3, Math.sin(a) * 0.25, -Math.sin(a) * 2.3); moon.rotation.y = -a; }
  belt.rotation.y = t * 0.02;
  if (saturnRing) ringC.copy(saturnRing.position);
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

let labelsOn = true;
function renderFrame() {
  const touring = mode === 'tour';
  updateWorld(worldT, touring ? tourT : fxT);
  if (touring && !free) {
    cameraAt(tourT, camState);
    camera.position.copy(camState.p); camera.lookAt(camState.q);
    controls.target.copy(camState.q);
  }
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
  tagEls.forEach((el, k) => {
    if (tagA <= 0) { el.style.opacity = 0; el.style.visibility = 'hidden'; return; }
    if (k === BELT) { nearestBeltSpot(tmp); tmp.y += 1.6; } else if (k === 0) tmp.set(0, SUN_R * 1.2, 0); else { tmp.copy(bodies[k - 1].orbit.position); tmp.y += PLANETS[k - 1].r * (PLANETS[k - 1].ring ? 1.4 : 1.2); }
    tmp.project(camera);
    if (tmp.z > 1 || Math.abs(tmp.x) > 1.2 || Math.abs(tmp.y) > 1.2) { el.style.opacity = 0; el.style.visibility = 'hidden'; return; }
    el.style.visibility = 'visible';
    el.style.opacity = tagA;
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
  orbitsOn = !orbitsOn;
  const b = $('orbits');
  b.setAttribute('aria-pressed', orbitsOn ? 'true' : 'false');
  b.title = orbitsOn ? b.dataset.on : b.dataset.off;
  b.setAttribute('aria-label', b.title);
});
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
let lastPerf = performance.now(), slow = 0, frames = 0;
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
    if (orbitsOn) worldT += dt;
    fxT += dt;
    dirty = true;
  }
  if (!visible || document.hidden) return;
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
  get loop() { return loop; }, get free() { return free; }, get focus() { return focus; },
  get flying() { return !!fly; }, get card() { return cardIdx; }, get dpr() { return dpr; },
  camera, controls, pick, select, setTime, render: () => renderFrame(),
};

const progressEl = $('progress'), loadText = $('loadtext');
const breathe = () => new Promise((r) => { let done = false; const go = () => { if (!done) { done = true; r(); } }; requestAnimationFrame(() => setTimeout(go, 0)); setTimeout(go, 60); });
function progress(x) {
  progressEl.style.transform = `scaleX(${x})`;
  loadText.textContent = T.loading(Math.round(x * 100));
}

(async () => {
  try {
    progress(0.05);
    await breathe();
    // textures are painted one planet per frame, so the loading bar keeps moving
    for (let n = 0; n < PLANETS.length; n++) {
      buildPlanet(PLANETS[n]);
      progress(0.08 + 0.84 * (n + 1) / PLANETS.length);
      await breathe();
    }
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
    hintEl.classList.add('is-on');
    hintTimer = setTimeout(hideHint, 9000);
  } catch (err) {
    fail();
    if (window.console) console.error(err);
  }
  requestAnimationFrame(tick);
})();
}
