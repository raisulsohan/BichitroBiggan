# Changelog

All notable changes to Bichitro Biggan are recorded here, newest first.

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
