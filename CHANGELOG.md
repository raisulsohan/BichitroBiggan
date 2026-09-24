# Changelog

All notable changes to Bichitro Biggan are recorded here, newest first.

## 7.21.2

- Home on the English edition is marked at last. The filter added in 7.21.1
  was never called: the theme's own BB_Nav_Walker builds each item's classes
  by hand and left out the nav_menu_css_class filter that core's walker runs,
  so anything adding a class to a menu item was dropped without a word. The
  walker runs it now, which is both the fix and one less way for the menu to
  ignore the rest of WordPress.
- Category items were never affected because their current mark is written
  into the item itself before the walker sees it. A custom link's is worked
  out by comparing addresses, and that comparison is what /en breaks.

## 7.21.1

- Home on the English edition was not marked as the page you are on, while
  every category was. Mine, from 7.19.1: pointing that custom link at /en/
  fixed where it goes and broke how WordPress recognises it. It decides which
  custom link is current by comparing the item's address against REQUEST_URI,
  and under /en the prefix has already been taken off REQUEST_URI by then, so
  an item reading /en/ never matched the bare / it was held against. The
  categories were never affected — those are taxonomy items, matched by term.
  The English Home is marked directly now.
- The back-to-top button keeps its line with the resume bar on a wide screen.
  It has always lifted itself clear when that bar appears, which is right on a
  phone where the bar runs nearly the full width, and pointless on a desktop:
  measured at 1400px the bar is a centred card with 354 pixels of clear floor
  beside it. Below 760px the lift stays.

## 7.21.0

- Category badges and section labels were being drawn in white on pale
  backgrounds: 2.4 against a sand, 2.9 against a pale blue, where a small
  letter needs 4.5. The category colours screen lets a text colour be chosen
  by hand and saved white without complaint. A choice is kept when it can be
  read and quietly corrected when it cannot — those two now come out at 7.4
  and 5.9 in near-black. Nobody picks a colour meaning to make the words
  disappear.
- The saved-articles drawer was hidden from a screen reader and still reachable
  by Tab, so the keyboard went somewhere the page said was not there. It is
  inert while closed now, which takes it out of both at once.
- The top bar and the ticker sat outside every landmark, leaving the date, the
  language link and the latest headlines in a part of the page a screen reader
  cannot navigate to by region. One banner wraps the lot, and the masthead
  inside it is a plain box — a page has only one banner.
- The lead story on the front page is an h2. It was an h3 straight after the
  site name's h1, which left a level out.
- The slider arrows, the moon, the envelope and the drawer's cross were all
  under the 24 pixels a fingertip is measured against. The icons are the size
  they were; the box you can hit is bigger.
- The sidebar no longer claims a complementary role it is not allowed to hold
  inside the main content.

## 7.20.4

- Every PNG on the site failed to convert — 46 pictures, some five hundred
  files counting their sizes — while all 388 JPEGs went through. An encoder
  that cannot manage a format is not always the only one installed, so a file
  that fails is now tried again with Imagick, and then with GD. They disagree
  about PNG often enough to be worth the second attempt. Each attempt is
  verified as before, so a broken write is thrown away rather than served.
- The screen explains itself on a second run. Nought files written and nought
  megabytes lighter read like complete failure when they meant there was
  nothing left to do, so there is now a count of the files that already had a
  WebP beside them, and the other two figures say "this time".

## 7.20.3

- A pass of the converter now stops at whichever comes first, forty pictures
  or fifteen seconds. A fixed three at a time was right for a first run, where
  every file has to be encoded, and painfully slow for a second, where nearly
  all of them are done and the work is a stat call — which matters, because
  repairing what an earlier run got wrong means starting from the beginning.
- A pass cut short by the clock now resumes exactly where it stopped rather
  than at the end of the batch it was given.
- The screen says how many could not be converted. Some cannot: a PNG with
  transparency can defeat the server's WebP encoder, which is what left two
  files of nothing behind. Those pictures keep their original and the page
  serves it, so the only cost is that they are not any lighter.

## 7.20.2

- A conversion that failed half way left a file of nothing behind, and the
  swap, which trusted a twin simply by finding one, served those zero bytes to
  readers in place of the picture. Two pictures on the front page were blank
  because of it. Nothing was lost — the originals were never touched — but the
  page was broken while it lasted.
- A written file is now checked before it is believed: it must have something
  in it and must read back as an image, or it is thrown away. An empty twin
  already on disk is ignored when serving and written again when converting,
  so re-running the tool repairs what the first run got wrong.
- The cards on the front page and the archives are built by hand rather than
  by wp_get_attachment_image(), so nothing was swapping their address. They
  serve the lighter twin now as well.

## 7.20.1

- The WebP swap was taking the srcset away with it. A picture's src was being
  changed to the .webp before WordPress had worked out its widths, and
  WordPress works those out by checking the src it was handed against the
  sizes recorded in the library — which record a .jpg. Finding no match it
  dropped the srcset and the sizes attribute entirely, so a 240px slot in a
  card stopped choosing a 300px file and downloaded the 800px original
  instead. Images on the front page went up, not down.
- Core is given the names it knows all the way through now. Only the finished
  HTML is touched: the srcset is rewritten after it has been built, and the
  src after the tag exists, where nothing reads it again. The preloaded
  largest image follows its own srcset, so a browser that ignores imagesrcset
  does not warm up a file the page never asks for.

## 7.20.0

- Tools → Pictures to WebP. Uploads have been converted on the way in for a
  while; everything from before that is still a JPEG or a PNG, and on the
  front page that was 372KB of 594KB — the heaviest thing left on a phone.
- It writes a .webp beside each old file — the original and every size made
  from it — a few pictures at a time from the browser, because a library of a
  few thousand will not finish inside one request on shared hosting. Closing
  the page stops it; opening it again carries on from the same place.
- Nothing is deleted, overwritten or rewritten. The uploads stay where they
  are, no article is touched, and where WebP comes out larger — a flat graphic
  sometimes does — the new file is thrown away and the original kept.
- A swap at render time serves the lighter twin: a picture whose address in an
  article still ends .jpg is given the .webp lying next to it. Because stored
  content is never changed, taking the filters away puts every page back to
  the file it always named.
- Only what a reader downloads. The dashboard, the block editor and the REST
  calls behind them go on seeing the library exactly as it is, and the sharing
  card keeps its JPEG — WhatsApp has been known to give up on a WebP preview,
  and there is no page weight to win there.

## 7.19.1

- Home on the English edition went to the Bengali front page. A category or a
  page in the menu gets its address from get_term_link() or get_permalink(),
  and both are filtered, so they arrived under /en already; a custom link does
  not — whatever was typed on the menu screen is what is stored. Custom links
  that point at this site are put through the same filter now. Links to
  anywhere else are left alone, and the Bengali switcher, which says what it is
  with hreflang=bn, still goes where it should.
- Hind Siliguri is gone. Three static weights, 215KB, to carry nine per cent
  of the text on an article and about forty elements on the front page —
  badges, section labels, the breadcrumb, the modal and footer chrome. Those
  are set in Noto Sans Bengali now, which every page was loading anyway.
- The Google tag is gone with it: a third-party connection and 72KB of unused
  JavaScript for figures the site's own Statistics already keeps, in more
  detail and without telling anybody who is reading. Search Console is
  unaffected — this site is verified by its HTML file, not by the tag.
- Fonts on a Bengali page: 545KB before this pair of releases, 130KB now.
  One file, not twelve.

## 7.19.0

- A phone was downloading the same 105KB of Bengali three times over. Noto
  Sans Bengali is a variable font: ask Google for 400, 600 and 700 and it
  answers with one file, three times, a different weight named on each. The
  script that fetches them saved three copies under three names, and a browser
  has no way to tell they are identical. With the Latin subsets too, that was
  260KB of a 788KB page fetched for nothing.
- The files are hashed now, identical bytes are written once, and the weights
  that shared a file are declared as the range they really are —
  font-weight: 400 700 on a single face. Nothing looks any different; there
  is simply two thirds less of it.
- The @font-face sheet is written into the page instead of fetched. It is
  under a kilobyte over the wire and it was costing a whole round trip of its
  own — on a phone, behind a connection and a handshake, most of half a second
  before any stylesheet could be read.
- The consent API's script is deferred. It sat in the head with neither defer
  nor async, so a phone stopped parsing to go and get it.
- Fonts on a Bengali page: 545KB down to about 285KB. One render-blocking
  request left instead of three.

## 7.18.3

- Most read and When they read no longer take the whole width. On a wide
  monitor the table put half a metre between an article's title and its
  figures, and twenty-four bars spread that far stopped looking like a day.
  They sit side by side now, half the width each.
- The two stand level, and the hours grow into whatever height the table
  beside them sets — an empty half-tile would have been the very thing the
  bento was built to get rid of. The bars are taller for it, and easier to
  read.
- Only the line across time keeps the full width. It is the one thing here
  that reads better the wider it gets.
- Most read shows eight articles rather than ten.

## 7.18.2

- The Statistics screen is a bento now: small tiles packed into columns, each
  one only as tall as what is inside it. Laying them out in rows had made
  every tile in a row as tall as the tallest one in it, so a tile of four
  lines beside a tile of twelve left eight lines of nothing — which is what
  made the screen feel enormous. It is about a third shorter.
- Three tiles still take the full width, because they earn it: the line across
  time, the table with six columns, and the twenty-four hours side by side.
  Everything else packs.
- Four columns on a wide monitor, down to one on a phone, decided by how much
  room there is rather than by a breakpoint.
- Smaller throughout: tighter padding, 12px corners, 13px tile headings,
  6px bars, a 150px chart and 84px hours where they were 180 and 120.
- The lists are shorter. Ten most-read articles rather than fifteen, ten
  countries and searches rather than twelve, eight of the rest. A tile that
  runs to fifteen rows stops being a tile.
- The title, the line under it and the five periods share one row instead of
  three.
- Came from is gone from the addresses that led nowhere — the address itself
  was always the part you act on, and the column cost a third of the tile.

## 7.18.1

- The Statistics screen is laid out properly. Everything on it had been packed
  into two enormous boxes — heading, table, note, next heading — with nothing
  between them, so the line under Most read sat welded to the bottom edge of
  the table. It had a negative top margin, written for a note that follows a
  heading rather than a table.
- Each section is now its own card: a title, the line that explains it, and
  the figures, with space of its own around them. Cards sit two to a row, and
  the ones that need the width — the chart, Most read, the hours of the day,
  the addresses that led nowhere — take the whole of it.
- The five periods are a single segmented control rather than five tabs welded
  to the top of a box that is no longer there.
- A rise or fall is a tinted chip now instead of a line of small text, so it
  reads at a glance in a card and in the Climbing table alike.
- The tables are the theme's own rather than WordPress's list tables with a
  class fighting them, which is what had pushed the figures out of line in the
  first place: quiet uppercase headings, hairline rules, a tint on the row
  under the pointer, figures in tabular numerals.
- Softer shadows, one palette declared once at the top instead of fifteen
  hex codes spread through the file, gradient bars, and a dark tooltip on the
  chart. On a phone the bars drop their track and keep the figures.
- The dashboard panel matches.

## 7.18.0

- Twelve more things the Statistics screen can tell you, all of them from the
  site's own tables and none of them about who a reader is.
- Climbing: what is being read far more today than over the week before. A
  piece written two years ago that suddenly moves is the kind of thing a list
  of totals hides.
- Where visits begin: the page a reader arrives on, which is rarely the home
  page and is worth knowing when deciding what the top of an article does.
- Pages a visit, beside the visit count, and which day of the week draws
  readers.
- Most read carries two new columns: how far down that article people get,
  and how long they stay on it.
- Searches that found nothing — the clearest list of the articles the site has
  not written yet.
- Links they followed out, how they shared it, and what was saved to read
  later: three things a reader does that the server never sees, counted by the
  page itself.
- A small panel on the dashboard's own front page: reading now, reads and
  visits today, and the three pieces being read.
- An Empty this list button under the addresses that led nowhere, for clearing
  out what has already been dealt with.
- Nothing about the reader is stored for any of it — no address, no cookie, no
  fingerprint. The new counters begin at this update; the days before it have
  nothing to show.

## 7.17.0

- An English article asked for without its prefix is no longer a dead end.
  /einstein-bohr-debate-quantum-uncertainty-bell/ is an English slug; on the
  Bengali side it belonged to nothing and returned a 404, although the article
  exists one directory along. Such an address now redirects to /en/<slug>/.
- The 404 list keeps the query string, so the ?p=<id> requests that make up
  most of a site's dead ends are listed one by one instead of piling up
  under "/" as a single meaningless line.
- Scanners are left off that list — /graphql, /.env, /wp-config, anything
  ending .php — along with /.well-known/ requests, which are browsers asking
  the site a question rather than failing to find a page. What is left is the
  addresses a reader really could not reach.

## 7.16.2

- The figures in the Most read, searches and 404 tables sit under their own
  headings again. WordPress aligns every table heading left with a selector
  that outweighed the theme's, so Bengali, EN and Total stood at the left of
  columns whose numbers were at the right. The digits are tabular now as well,
  so they line up with each other down the column.

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
