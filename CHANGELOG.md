# Changelog

All notable changes to Bichitro Biggan are recorded here, newest first.

## 7.16.1

- Hours read in am and pm, on the chart, in what the pointer reveals and under
  the hour-of-day columns: 2 pm rather than 14:00.
- The chart is a third of the height it was. It had been drawn to keep its
  proportions, so a wide monitor made it nearly 500 pixels tall and the screen
  was mostly chart. The plot is a fixed 180 pixels now (150 on a small screen)
  whatever the window does — the scale, the dates, the guide and the dot are
  laid over it as ordinary HTML, so nothing stretches with it.

## 7.16.0

- The chart on the Statistics screen reads like a chart now: a scale up the
  left at round figures, faint lines across it, the dates along the bottom,
  and — where it was most missed — the exact number under the pointer, with a
  dotted guide and a dot on the day it belongs to. It works by touch as well.
- Still one inline SVG and forty lines of script. No charting library is
  loaded for it, and the screen makes no request of its own.

## 7.15.1

- The switch in the top bar and in the sticky menu spells the language out —
  English, Bangla — with the same globe beside it as the pill on an article.
  Two switches that do the same thing now read the same way. On a narrow
  phone the date shortens rather than the buttons wrapping.

## 7.15.0

- **Where in the world** — which countries readers are in, without ever seeing
  an address. The page sends the browser's own time zone; Asia/Dhaka is
  Bangladesh and nothing else. No lookup service is called, nothing about the
  reader leaves the site, and what is stored is two letters on a counted row.
  A country header from a CDN is preferred when the host sends one.
- **How far they read** — a view says an article was opened; this says whether
  it was read. The furthest point reached is measured as the reader leaves and
  rounded to a quarter, with the average across the period above it.
- **Addresses that led nowhere** — every 404 a reader hit, most asked first,
  with where the bad link was on. The one thing on the screen that can actually
  be fixed.

## 7.14.0

- Statistics opens on **Last 24 hours**, and the chart under it is hour by
  hour. Counting is kept by the hour now rather than by the day, so a morning
  can be told from an evening.
- **Right now** — how many reads in the last half hour, on a card of its own.
- Every figure is compared with the period before it: 1,240 reads, ▲ 18% on the
  previous 24 hours. A quiet week no longer has to be worked out by eye.
- **When they read** — the hour-of-day pattern across the whole period, so the
  shape of a day is visible: when to publish, when nobody is there.
- **What they searched for** — the words readers type into the site's own
  search box, most asked first, with the edition they asked from. The surest
  list of what the site is missing.
- Articles and everything else are counted apart, so a homepage read is no
  longer confused with an article read.
- Rows recorded before this update have no hour of their own and are all
  counted at midnight in the hour-of-day pattern; every other figure is
  unaffected.

## 7.13.1

- "পরে পড়ুন" was coming through untranslated on the saved-articles button.
  ড়, ঢ় and য় can each be written two ways in Unicode — one character, or the
  letter plus a nukta — and gettext matches byte for byte, so a msgid typed one
  way and a translation stored the other silently failed while looking
  identical on screen. The compiler now writes both spellings of every string
  into the .mo, which closes the whole class of failure rather than this one
  instance of it.
- A video embedded by pasting its address carried the title YouTube holds for
  it, which is Bengali; on /en the article's own English title is used instead,
  so a screen reader announces the right thing.

## 7.13.0

- The dashboard speaks English. Every screen the theme adds — Theme Settings,
  Category Colours, Statistics, the SEO box and its analysis, the references
  and video boxes, the profile fields, the licence page, the update notices —
  was written in Bengali; all of it now reads in English, including the
  wording inside, the numbers and the dates.
- The site itself is untouched: every word a reader sees is still Bengali
  (and English under /en). Only the screens behind the login changed.
- The SEO analysis reads in English too — "The title is a good length
  (54 / 60 characters)", "Overall: Good — 6 checks passed" — since it is
  written for whoever is editing the post, in the dashboard.

## 7.12.0

- The site keeps its own statistics, on its own screen: Dashboard →
  পরিসংখ্যান. Reads per day as a chart, the most-read articles, where readers
  came from, what they read on, and how the two editions compare — over 7, 30,
  90 or 365 days. No account to sign in to and no permission that can be
  withdrawn.
- The reading beacon the theme already used now reports every page rather than
  only articles, and carries the edition, the referring site and whether this
  is the first page of the visit. It is still sent from the browser, so a page
  served from the cache is counted and a crawler is not.
- Nothing that identifies a reader is stored: no address, no cookie, no
  fingerprint — one row per day per combination, counted up. Visits by anyone
  who can edit posts are left out, so the people running the site do not
  inflate its figures.

## 7.11.0

- A language switch on the article itself, opposite the category badge: from a
  Bengali article it opens that same article in English, and from the English
  one it comes back to the Bengali. Inside the reading popup it swaps the
  article in place instead of closing the popup for a page load.
- It appears only where there is somewhere to go. Most articles have no English
  version yet, and a button that drops the reader on a front page instead of
  the piece they were reading is worse than no button at all.
- Everything the script writes on the page is translatable now — the quote
  tooltip's Copy and Share buttons, the saved-articles drawer, the toasts, the
  video popup's labels. They were typed into theme.js in Bengali, where no
  translation could reach them, so the English edition showed Bengali buttons
  on an English page.

## 7.10.5

- The structured data on an English article carried the Bengali headline. The
  schema and the title fallback read the post title straight from the database,
  where the rest of the page reads it through the filter that swaps in the
  English one; all three read it the same way now. Bengali pages are unchanged.

## 7.10.4

- The hreflang tags drop the query string. A page reached with ?utm_source= or
  a cache-buster on it was offering that exact address to search engines as the
  other language of itself; they now point at the clean address. The switch in
  the top bar still keeps the query, so a filtered archive stays filtered when
  the reader crosses over.

## 7.10.3

- The English edition answers with its own SEO fields wherever the site asks
  for them, not only where the page context is built. The keywords tag was
  still printing the Bengali focus keyphrase and its synonyms on /en.
- A tag nobody has translated yet is left off an English article — in the list
  under it and in what it reports to search engines. Categories are unaffected:
  they always have an English name. As tags are translated they appear.
- Writers carry their English name and biography through the REST API now, so
  a script can fill them in; the same two fields remain on the profile screen.

## 7.10.2

- The English edition can wear a logo of its own — Appearance → Customize →
  English edition (/en) → Logo for the English edition. Left empty, /en keeps
  the Bengali logo.
- The logo no longer arrives as a full-width image on a phone. WordPress tells
  the browser an image may fill the window, so a phone was downloading the
  1024-pixel copy of a logo painted about 160 pixels wide; the theme now
  states the real drawn width, worked out from the masthead height and the
  image's own proportions, and registers a 400-pixel size for it to pick.
- The English title tag. The SEO engine reads the Bengali SEO title straight
  from the post, bypassing the filter that swaps the rest of the English
  fields in, so /en pages carried a Bengali <title> while their description,
  og:title and schema were English.
- A small REST route, bb/v1/logo, sets either logo from a script for anyone
  who could set it in the Customizer.

## 7.10.1

- Everything around an article now reads English under /en, not just the
  article. A category with no English name of its own takes it from its own
  slug — space-science becomes "Space Science" — so the badges, the section
  headings and the category strip are English from the first load, and a name
  typed into Categories → English name still wins.
- Menu labels follow. A label written in Bengali is replaced by the English
  name of the category it points at, or by the theme's own translation of it,
  which is how "প্রথম পাতা" reads "Home". A label already written in English is
  left alone, so a menu built for /en keeps its own wording.
- The month list in the sidebar was cached once and served to both editions,
  which left Bengali month names under /en. Each edition caches its own now.
- The switch itself reads EN on the Bengali site and BN on the English one —
  each side in its own language, tooltip included.

## 7.10.0

- An English edition at `/en`. The same site, the same design, the same
  articles — read in English. A small **EN** button sits beside the dark-mode
  switch in the top bar and in the sticky menu; on `/en` it turns into **বাং**
  and leads back to the Bengali page it came from.
- Every post now has an *English edition* box under the editor: English title,
  slug, excerpt, article, SEO title, meta description and focus keyphrase, plus
  a **Ready** tick. Until that tick is on, `/en` does not show the post at all —
  no half-translated article, and no Bengali article pretending to be English.
  An **EN** column in the Posts list says at a glance where each one stands.
- The same fields are open to the REST API, so a translation can be delivered
  by a script signed in with an Application Password, exactly like a new post.
- Categories, tags and writers carry English names of their own (and writers an
  English biography), set on their own edit screens. `/en` can also have its own
  menu — Appearance → Menus → *Primary Menu (English edition)*.
- The interface itself speaks English under `/en`: `languages/en.po` holds the
  translations, `npm run mo` compiles them. Dates, numerals and reading times
  follow — "22 September, 2025" and "6 min" rather than "২২ সেপ্টেম্বর, ২০২৫"
  and "৬ মিনিট".
- Search, the reading modal, the feed, pagination, the previous/next links and
  the footer columns all stay inside the edition they were opened from, and
  only ever offer translated articles.
- For search engines: `hreflang` tags on both editions pointing at each other,
  `og:locale` and the schema's `inLanguage` following the language, and an
  English sitemap of its own at `/wp-sitemap-en-1.xml`.
- The wording around the articles — the tagline, the footer headings, the About
  text — has English twins in Appearance → Customize → *English edition (/en)*.
  Left empty, each falls back to built-in English, never to the Bengali text.
- Theme Settings → Advanced takes a **Google tag (GA4 Measurement ID)**. Entered
  there, the Google tag is printed on every page of both editions, which is what
  Analytics looks for when it checks whether a property really belongs to a site.

## 7.9.1

- Restore year-wise archive tabs and latest-posts ticker on a static front page.
  The condition hiding them on single posts had used `! is_singular()`, which
  WordPress also evaluates as true for a static homepage page, silently
  dropping them. They now display on the front page as intended while remaining
  hidden inside individual articles and pages.

## 7.9.0

- An SEO column in the Posts and Pages lists: a red, amber or green dot per
  post, the same traffic light the SEO box shows in the editor, so the posts
  that need work stand out without opening each one. Hovering it lists what
  holds the score back — no focus keyphrase, keyphrase missing from the title,
  a description too long, no featured image.
- The score is worked out from the saved fields by the editor's own rules,
  including the Yoast values older posts still fall back to. The two were run
  side by side over 576 combinations of keyphrase, title, description and
  image, and agreed on every one.

## 7.8.1

- The সূচিপত্র and পরে পড়ুন buttons float on a desktop again. They did float,
  but stopped 56px from the top, and the stuck menu on a desktop is 106px tall:
  both buttons sat entirely behind it. Phones were fine only because the phone
  menu happens to be 57px. They now stop 8px under wherever the menu really
  ends, measured by the script — which also covers the admin bar pushing the
  menu down for a signed-in editor. The popup was never affected.
- A সূচিপত্র link no longer scrolls its heading behind the menu; it lands below
  the menu and the floating buttons.

## 7.8.0

- The SEO box's fields — focus keyphrase, keyphrase synonyms, SEO title, meta
  description — and the তথ্যসূত্র list can be set through the REST API. A post
  sent in by a publishing script, signed in with an Application Password,
  arrives with them already filled in instead of waiting to be typed.
- Writing them takes the same permission as editing the post. Reading them
  shows nothing the page does not already print in its head.
- Nothing changes in the editor, and posts that have these saved keep them.

## 7.7.6

- No change to the theme itself. The commit history was rewritten to drop an
  attribution trailer that did not belong in this repository; every commit is
  the author's own. This release carries a version number to match.

## 7.7.5

- The strip arrows keep out of the way: invisible until the pointer is over
  the strip, then they fade in. Still nothing at all when every category fits.
- They stay reachable by keyboard — the arrow appears when it takes focus, so
  Tab still finds it.

## 7.7.4

- The category strip is back at its original size — 15px, 14px padding. 7.7.2
  had shrunk the type to make ten categories fit; now that the arrows carry the
  reader along there is no reason to shrink anything. The strip runs 35px past
  its track, so the arrow is there from the first page load.

## 7.7.3

- Arrows on the category strip. When there are more categories than fit, a
  round ‹ / › button appears on the side there is something to scroll towards
  and slides the strip along. They are real buttons, so the keyboard reaches
  them, and they hold still for anyone who has asked for less motion.
- Nothing appears while the categories fit, which is the case today.
- The strip jumps rather than glides: neither scrollBy({ behavior: 'smooth' })
  nor scroll-behavior: smooth could be relied on — in testing the second one
  stopped the scroll happening at all — and an arrow that does nothing is worse
  than an arrow without an animation.

## 7.7.2

- The category strip actually fits now. 7.7.1 left it fitting 1,220px in
  exactly 1,220px, which is not fitting at all once a font renders a pixel
  wider — and the fade meant to hint at scrolling just made the last category
  look washed out. At 14px it comes to 1,082px, leaving about 100px spare.
- Dropped the rule that restored the larger size above 1,400px: the strip is
  capped at 1,220px however wide the window is, so that width had exactly as
  little room and cut the last item again.
- Checked at 1,280, 1,377 and 1,500 pixels.

## 7.7.1

- The dark mode switch is in the sticky bar too, so it stays within reach once
  the top bar has scrolled away.
- The category strip needed 1,255px inside 1,220px at a 1,280px window, and its
  scrollbar is hidden — so "ভিডিও" was cut in half with nothing to say why.
  Tighter link padding fits the ten categories there are; when there are more
  than fit, the strip now fades at its right edge to show it scrolls.

## 7.7.0

- Theme Settings → ক্যাটাগরির রং: every category's badge colour and its text
  colour, each shown as the badge itself. Three options per category — অটো,
  সাদা, কালো — with the contrast of each measured beside it. Click the badge
  that reads best.
- Fixes the automatic choice itself: it measured against pure black while
  printing #1a1a1a, and on the orange of বিজ্ঞান ও প্রযুক্তি that flipped the
  answer — dark text at 3.9:1 over white at 4.4:1. White is back, and the
  measurement is no longer the last word either way.

## 7.6.2

- Author pages no longer carry the login name. The public address is built
  from the display name — /author/raisul-sohan/ rather than /author/sohan/ —
  and the old address redirects to it. Nothing in the database changes; the
  address can be set per author on the profile screen.

## 7.6.1

- Dark mode reaches the places the generator could not see: three-digit hex
  colours, panels written as rgba(), and a variable with a fallback value. The
  navigation, the YouTube button and the contents toggle were unreadable.
- tools/contrast-check.js: paste it into the browser console and it lists every
  piece of text that falls below WCAG AA, in whichever theme is showing.
- Colour fixes it found, in both themes: badge and section-heading text now
  picks black or white by measurement; the share buttons use accessible shades
  of their brand colours; the footer credit line, category counts, resume bar,
  previous/next links and contents caret all read properly.
- Login names are no longer public: the users REST endpoint is closed to
  logged-out requests, /?author=1 no longer redirects to an account, the
  authors sitemap is gone and oEmbed stops carrying the author URL. Author
  pages themselves are unaffected.

## 7.6.0

**Reading**
- Dark mode: follows the phone or computer's own setting, with a switch in the
  top bar that is remembered. The theme is chosen before the page is painted,
  so there is no white flash. (The old dark mode could not work — the script
  removed the theme attribute on every load.)
- A তথ্যসূত্র box in the editor prints a numbered source list under the
  article and adds the same sources to its structured data. Posts without one
  are unchanged.
- Equations render with KaTeX, loaded only on posts that contain any.
- Related posts are matched by tag before falling back to the category.
- Author pages: a real page per writer, with their biography and their work.

**Performance**
- The typefaces are served from this site instead of Google: no third-party
  connection before the first Bengali glyph, and no reader's address handed
  over. Two unused weights dropped; 18 files, 764KB, of which a reader
  downloads only the subsets a page needs.
- npm run build writes style.min.css (116KB → 84KB) and theme.min.js
  (63KB → 33KB); the theme serves them only when the build is current.

**Housekeeping**
- functions.php split: formatting, images, views, verification and the licence
  moved into inc/.
- One set of Bengali date helpers instead of two, and one .bb-single__title
  rule instead of two.
- The post pickers in Theme Settings and the Customizer search the whole
  archive rather than offering the newest 400 posts.
- Site-specific defaults gathered into bb_default(), filterable.
- .gitignore, CHANGELOG.md, phpcs.xml.dist, a translation template with 301
  strings, and the generators under tools/.
- Tested up to WordPress 7.1.

## 7.5.0

**Performance**
- Reads are counted from the browser instead of `wp_head`, which a page cache
  silenced and crawlers inflated. Per-day counts mean the footer's "এই সপ্তাহে"
  now lists what was read this week, not what was published this week.
- Card images carry `srcset`/`sizes`, so phones stop downloading desktop crops.
- Podcast cards no longer load a full-size image behind a 360px tile.
- Two unused font weights dropped (10 faces → 8).
- The sidebar archive query is cached; reading time is stored on the post.

**Accessibility**
- A visible focus ring; several inputs had `outline: none`.
- Focus stays inside the article, search, bookmark and video dialogs, and
  returns where it came from on close. Escape closes the video dialog.
- The ticker has a pause control and does not autoplay under
  `prefers-reduced-motion`.
- Body text at `#9ca3af` raised to `#6b7280` (2.5:1 → 4.8:1).
- Bengali body text is left-aligned on phones, and pasting into the editor no
  longer bakes `text-align: justify` into the post.

**Bengali interface and sharing**
- The remaining English labels, page counts and comment counts are in Bengali.
- Share buttons are named: ফেসবুক, হোয়াটসঅ্যাপ, টেলিগ্রাম, এক্স, লিংক কপি, plus the
  device's own share sheet. Pinterest removed.

**Fixes**
- `#f3.6.0` was an invalid colour in 22 places, so those borders and
  backgrounds never rendered at all.
- The bookmarks drawer is built from text nodes and validates stored URLs.
- The popular-posts filter uses the localised admin-ajax address.
- Video URLs are saved with `esc_url_raw()`.
- `og:image` is 1200×630 and carries width, height and alt.
- Articles show a "সর্বশেষ হালনাগাদ" date; cards hide the comment icon at zero.

## 7.4.0

- A missing licence no longer blocks the public site. It withholds theme
  updates; visitors and crawlers are never served a 403.
- The hard-coded Google Search Console token moved into
  Theme Settings → Advanced, so no other site serves this one's token.
- `lang="bn-BD"` and `inLanguage` are set from the content language rather than
  the admin locale.
- The homepage has a real title, a meta description and an `h1`.
- `max-snippet` and `max-video-preview` carry their `-1` values.
- `/page/N/` past the last page returns 404 on a static front page.
- The popular-posts endpoint caps `count` at 8 and caches for ten minutes.
- Uploads use WordPress's own WebP conversion: originals are kept, and a
  same-named `.webp` is no longer overwritten.
- The SEO slug field saves through `wp_update_post()`, so slugs stay unique and
  old links redirect. It no longer undoes edits made in the permalink editor.
- Removed the script that stripped UTM and `fbclid` before Analytics loaded.

## 7.3.1 and earlier

See the commit history on GitHub.
