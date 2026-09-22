# Bichitro Biggan (Custom WordPress Theme)

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg?logo=php&logoColor=white)](https://php.net)
[![Version](https://img.shields.io/badge/Version-7.13.0-0080ff.svg)](style.css)
[![Zero-Plugin Architecture](https://img.shields.io/badge/Plugins-0%20(Built--in)-success.svg)](#-key-features)
[![Responsive](https://img.shields.io/badge/Responsive-Mobile%20%26%20Desktop-brightgreen.svg)](#-key-features)

Bichitro Biggan is a modern, super-fast, and completely zero-plugin classic WordPress theme built for a Bengali science magazine and digital publication. Custom-made and developed from scratch by Raisul Sohan, it is designed with a world-class reading experience, built-in SEO, live search, and comprehensive customizer controls.

---

## 📑 Table of Contents
1. [Key Features](#-key-features)
2. [Reader Experience](#-reader-experience)
3. [Built-in SEO Engine](#-built-in-seo-engine)
4. [Customizer Controls](#-customizer-controls)
5. [Installation & Setup](#-installation--setup)
6. [Tech Stack](#-tech-stack)
7. [License](#-license)

---

## 🌟 Key Features
- ⚡ **Zero-Plugin Architecture:** No heavy third-party plugins. SEO, reading modal, live search, and bookmarks are completely native, built with PHP and Vanilla JavaScript.
- 🎨 **Figma Mac & Magazine Layout:** Visually stunning 8-card hero mosaic, category tab grid, and multi-column sections.
- 📱 **Mobile First Fully Responsive:** Automatic adaptive grid and touch-friendly navigation for mobile, tablet, and large desktops.
- 🚀 **Super Fast Performance:** No jQuery on the frontend, self-hosted fonts, minified assets, and a lightweight CSS variable architecture.
- 🌙 **Dark Mode:** Follows the reader's own system setting, with a switch in the top bar that is remembered. Chosen before the page paints, so there is no flash.
- 📊 **Own Statistics:** Dashboard → পরিসংখ্যান. Reads per day, most-read articles, where readers came from, device and edition splits — counted by the site itself, in its own table, with no third-party account and nothing stored that identifies a reader.
- 🌐 **English Edition at `/en`:** The same site in English, from one install. A small **EN** button beside the dark-mode switch crosses over; each post carries its own English title, article and SEO fields, and only appears under `/en` once it is marked ready. hreflang, an English sitemap and an English menu come with it.

---

## 📖 Reader Experience
- **Distraction-Free Modal:** Clicking an article opens an instant cinematic reading modal using native AJAX (zero page reloads).
- **Cinematic Video Popups:** Site-wide support for immersive video popups from YouTube, including dynamic aspect ratios (16:9, 9:16, 4:5, 1:1) and custom settings.
- **Interactive Bookmarks:** Built-in read-it-later functionality via localStorage with a dedicated slide-out drawer.
- **Smart Typography:** Systematically scaled Bengali typography optimized for long-form reading on all screen sizes.
- **তথ্যসূত্র (Sources):** A box in the editor prints a numbered source list under the article — and puts the same sources into its structured data.
- **Equations:** KaTeX renders maths, loaded only on the posts that contain any.
- **Author Pages:** A page per writer, with their biography and everything they have written.

---

## 🔎 Built-in SEO Engine
- **Yoast-like Meta Box:** Custom meta box in the classic editor to define SEO titles and descriptions.
- **Dynamic Google Preview:** Real-time desktop and mobile search snippet preview with dynamic tags (%title%, %sitename%).
- **Automated Open Graph:** Automatically generates Facebook/Twitter meta cards with optimized fallback images.
- **JSON-LD Schema:** Outputs proper Article and Organization structured data for Google Rich Results.

---

## ⚙️ Customizer Controls
Fully integrated with the WordPress Customizer for live previews:
- Toggle core features like AJAX Reading Modal and Live Search.
- Reorder Homepage categories dynamically using dropdowns.
- Choose custom accent colors and adjust container dimensions (Hero mosaic height, sidebar sizes).

---

## 🚀 Installation & Setup
This theme features a **Native GitHub Auto-Updater**, meaning you only need to install it manually once. All future updates will be delivered directly to your WordPress dashboard without needing any third-party plugins.

### Initial Installation:
1. Click the green **Code** button on this GitHub repository and select **Download ZIP** (or download the latest release).
2. Go to your WordPress Dashboard: **Appearance > Themes > Add New > Upload Theme**.
3. Upload the downloaded ZIP file directly and click **Install Now**, then **Activate**.
*(Alternatively, compress the repository folder directly as `bichitro-biggan.zip` and upload it).*

### 🔄 Future Updates (Auto-Updater):
You are all set! Whenever a new version is released on GitHub, you will see a standard "Update Available" notification in your WordPress dashboard. Just click **Update Now** and the theme will automatically pull the latest code.

---

## 🔑 Licensing & Purchase
This is a premium, custom-made theme. A valid **License Key** unlocks automatic updates and support. The site itself always runs, with or without a license — the key never takes a live site offline.

**How to get a License Key:**
To purchase the theme and receive your unique license key, please contact me directly:
- **Portfolio & Contact:** [raisulsohan.com](https://raisulsohan.com)
- **Email:** You can reach out through the contact form on my portfolio.

**How to Activate:**
1. Once the theme is installed and activated, go to **Appearance > Theme License** in your WordPress dashboard.
2. Enter the License Key provided to you upon purchase.
3. Click **Activate License**. Automatic updates are now enabled.

---

## 💻 Tech Stack
- **Frontend:** Semantic HTML5, Vanilla Modern CSS3, Vanilla JavaScript (ES6+).
- **Typography:** Hind Siliguri and Noto Sans Bengali, served from the site itself (SIL Open Font License) — no third-party connection before the first Bengali glyph.
- **Backend:** WordPress Native PHP APIs, Custom Transients Caching, Secure AJAX Nonce validation.
- **Standards:** WordPress Theme Review Guidelines, WCAG 2.1 Accessibility & Core Web Vitals optimized.

---

## 🛠 Development

The theme runs as it is — none of this is needed to use it. These scripts
regenerate the parts that are built rather than written by hand:

```bash
npm install          # one time
npm run build        # style.min.css and theme.min.js
npm run dark         # rebuild the dark palette from the light rules
npm run fonts        # re-download the font files and their @font-face sheet
npm run pot          # refresh languages/bichitro-biggan.pot
npm run mo           # compile languages/en.po into the .mo /en reads
npm run lint:php     # parse every PHP file and report syntax errors
```

To check that nothing is unreadable, open any page of the site, press F12, and
paste `tools/contrast-check.js` into the console. It measures every visible
piece of text against what is actually behind it and lists whatever falls below
the readable threshold, worst first. Run it once in dark mode and once in light.

The theme serves `style.min.css` and `theme.min.js` only when they exist **and**
are newer than their sources, so an un-run build never ships stale code.

Change any light-mode colour and run `npm run dark` — the dark palette is
generated from the stylesheet, not maintained separately.

## 📝 License
This project is licensed under the **GPL-2.0-or-later** license.

**Developed & Designed by:** [Raisul Sohan](https://raisulsohan.com) | [Bichitro Biggan](https://bichitrobiggan.com)
