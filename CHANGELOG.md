# Changelog

All notable changes to Bichitro Biggan are recorded here, newest first.

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
