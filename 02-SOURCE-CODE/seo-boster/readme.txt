=== SEO Boster ===
Contributors: Rajvardhan Singh Rathore
Tags: seo, keywords, on-page seo, yoast, rank math, semrush, keyword research, schema
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later

Comprehensive Agency-Grade SEO Automation Suite. Built-in Autonomous Keyword Research & Search Volume Engine (SEMrush Alternative), Rich JSON-LD Schema Markup Engine, Deterministic Rank Math 85+ Scorer, Divi Image Alt Fixer, and 1-Click Rollback Safeguards.

== Description ==

= Keyword Adder =
1. Go to SEO Boster > Keyword Adder and paste your keywords, one per line.
2. Click "Find the right pages". The plugin scores every published page using its title, URL,
   Yoast / Rank Math focus keyphrase and page text, with synonyms (corp = corporation,
   LLC = limited liability company, WY = Wyoming) so it understands what each page is about.
3. Review: every keyword shows the chosen page, how strong the match is, other pages that fit,
   and the line that will be added. Change anything or skip a keyword.
4. Click "Add keywords to pages".
5. The report shows each page with its link, the line number, the exact text added and a
   direct link that jumps to that line (page-url#seob-line-3). Export it as CSV.

Nothing in the page's own content is rewritten. The lines are added when the page is shown,
so it works with Divi, Elementor, Gutenberg and the classic editor, and any keyword or whole
run can be undone in one click from the report.

= Security =
* Only users with manage_options (filter: seo_boster_capability) can use it.
* Every action checks a nonce and the user's right to edit that page.
* Keywords are always read from the server copy, never trusted from the form.
* All input is sanitized, all output escaped, all SQL prepared.
* CSV export blocks spreadsheet formula injection.
* Delete the plugin and uninstall.php removes its table, options and page data.

= Adding new SEO features =
Each feature is a module. Create modules/<name>/class-seob-<name>.php, extend SEOB_Module
(id, name, description, render_page, optional register and stats), require it in
seo-boster.php and add it in SEOB_Plugin::core_modules(). It gets its own menu page,
dashboard card and on/off switch automatically.

Other plugins can add modules with the filter:
add_filter( 'seo_boster_modules', function ( $modules ) { $modules[] = new My_Module(); return $modules; } );

= Hooks =
* seo_boster_capability: capability needed (default manage_options)
* seo_boster_modules: register modules
* seo_boster_excluded_ids: page IDs that never get keywords
* seo_boster_sentence_templates: change the line templates
* seo_boster_keywords_applied: action after a run ( $batch_id, $post_ids )

= Area Pages =
Replaces the old manual process (ACF import, CSV importer, ChatGPT CSV, Theme Builder).
1. AI settings: choose any AI (Claude, OpenAI, Gemini, DeepSeek, OpenRouter, Groq, Mistral, Grok or any
   OpenAI-compatible API) and paste the API key (stored encrypted, never shown again). Test it.
2. Design: pick Built-in template (any theme), Divi (imports the SCO layout into Theme Builder
   for Areas We Serve) or Elementor Pro (imports the Elementor layout as a Single template).
   Layout colors are swapped for the site's brand colors; links to old client sites are fixed.
   Divi and Elementor layouts need the free ACF plugin active.
3. Create pages: business, services, keywords (one line per service, comma separated),
   locations, phones, images per service from the Media Library. Click Create pages.
4. Every Service x Location page is written one at a time in the background (keeps going if
   the tab is closed), checked against the team rules (word counts, location in headings,
   title case, banned words, no repeated sentences) and fixed automatically by asking again.
   Titles, phones (tel:+1...), contact links and image rules are filled by code, never by AI.
5. Report: progress bar, every page with keyword, status, View and Edit links,
   Publish all drafts, Retry failed, Move pages to trash. Pages get Yoast / Rank Math title,
   description and focus keyphrase, the Location term, a featured image and FAQ schema.

The plugin creates the Areas We Serve post type, Location taxonomy and all ACF fields itself
(same names and keys as the team's ACF export, plus the 3 fields the Elementor layout needed).

= Page layout editor (Area Pages > Page layout) =
One layout for every Area page, built into the plugin, so it works with any theme or builder.
Show or hide sections, drag to reorder, duplicate, delete, add Custom text or Buttons sections,
change backgrounds, spacing, alignment, button text, columns, image side, hide per device,
and set brand color, buttons, corners, heading size and image shape for all pages.
Live preview on a real Area page (desktop, tablet, phone). Nothing changes on the site until
you press Save layout. Export a layout and import it on another site.

= One-click setup (Area Pages > Setup) =
Installs and activates ACF if needed and imports the team's ACF export (Locations field group,
Areas We Serve post type, Location taxonomy) into ACF, like a manual ACF import. Safe to run twice.

= Automatic images =
Nothing to pick by hand. Every page gets its own matching photos from the Media Library (different
photos on each location page), with SEO file names and alt text. With a free Pexels API key (AI & images
tab) it also downloads stock photos when the library has too few. "Auto-fill images" shows the first
page's photos if you want to check or change them.

= Design in the Theme Builder, like before =
"Set up this site" finds the site builder. On Divi it puts the SCO layout into Divi Theme Builder
as a template for "All Areas We Serve"; on Elementor Pro it adds an Elementor single template with
the same condition. Edit it there once and every Area page changes. Sites with no builder get the
same SCO design from the plugin (Area Pages > Design). Colors are matched to the site brand, and a
template that already exists is kept so your Theme Builder edits are never overwritten.

= Menu and hub page (Area Pages > Menu & hub page) =
Automatic: "Areas We Serve" > Locations > Services (or Services > Locations) is added to the site's
live header menu by itself; only these items are ever changed, and it updates itself when pages are
published or removed.
Option 2: an "Areas We Serve" page with a banner and one card per location listing its services.
Drag locations and services to set the order for both.

= Data =
Everything is stored in each site's own database. Nothing is shared between sites.

== Changelog ==
= 2.0.0 =
* Complete Integration of SEO Pilot Architecture and Rich Schema Engine.
* Rich JSON-LD Schema on Every Page: LocalBusiness / Organization, Service schema, FAQPage schema (auto-extracted from ACF fields and Divi accordions), BreadcrumbList, and WebPage schema.
* Core Pages Optimizer Module: Audit and calibrate static site pages (Home, About, Services) to 85+ Rank Math score with strict heading structure and 1-click rollback snapshots.
* SEMrush Bulk CSV Drop-In & Clustering: 1-click query generator helper ({Service} {City}) and drag-and-drop CSV parser that clusters keywords by highest Volume and lowest KD%.
* Deterministic Rank Math 85+ Postmeta Bridge: Precise mathematical scoring calibration (Title, Description, Keyword Density 1.0-1.4%, Slug, Subheadings, Robots index, Yoast fallback).
* Divi Blurb & Masonry Gallery Image Alt Fix: Tier 1 shortcode injection (`image_alt`) and Tier 2 runtime front-end `the_content` filter (priority 999) ensuring 0 missing alt tags.
* Side-by-Side Review & Approval Table: Human-in-the-Loop review table showing projected scores and keyword metrics with 1-click batch publishing.
* 1-Click Rollback Snapshot Safety: Automatic snapshots stored in postmeta before page modifications.

= 1.9.0 =
* New Divi Theme Builder layout (5 sections: hero, photo with text and buttons, colored section with photo, FAQ, call-to-action with background photo).
* The layout's brand colors are read from the layout itself and changed to the site's brand colors; black, white and greys stay.
* Fonts, heading sizes (desktop, tablet, phone), text, FAQ and buttons follow the site where the plugin is installed. Big display headings (H5, H6) use the site's H2 style.
* Image grid and Auto-fill show only the photos the installed template uses.
* Buttons hidden by design stay hidden; only phone-only hiding is removed.

= 1.8.5 =
* Buttons: the main button is now the site's call-to-action (the filled button in the hero), the second is the next filled style. Buttons on Area pages are always an exact copy (other button plugins' default CSS can no longer override them).
* A button that blends into its section switches to the site's other button style (or a white / dark version), so every button stays visible.
* Theme Builder template fixes (run once, fonts and your edits stay): hero gets the page photo with a dark layer, sections with white text on a light color get a darker shade, FAQ titles readable on every screen (sizes for desktop, tablet, phone and 1.35 line spacing), "Contact Us" no longer hidden on phones.
* Button pairs sit in one neat row that wraps on small screens; on very narrow phones they are full width.

= 1.8.4 =
* Auto-fill images now fills every cell for every service: matching photos first, then good general photos of the business (each service gets different ones), Magnific photos when the library has too few, and the site icon or logo for the Section 5 icon on every row.
* Photos picked by hand are never replaced by Auto-fill. Pressing Auto-fill again refreshes only the auto-filled cells.
* With Magnific, auto-filled photos are a preview and fallback: every page still gets its own new photos.

= 1.8.3 =
* Fixed: Areas We Serve dropdown piled up on Divi Theme Builder headers. The Divi mega-menu class is no longer used (and is removed from menus built before); locations now show as a clean list and each opens its services beside it, to the left when there is no room on the right.
* Phone menu: every location shows an arrow and opens with a tap, even when the theme hides nested arrows.

= 1.8.2 =
* Divi and Elementor sites: the site's fonts, sizes, line spacing and buttons (colors, corners, border, padding, hover, shadow) are written into the Theme Builder template itself, once. All later design changes are made in the Theme Builder and show on every Area page; the plugin no longer forces typography CSS on those pages.
* Removed the SEO Boster layout editor on Theme Builder sites (it stays only for sites without a builder).
* Setup tab: "Edit design in Theme Builder" and "Copy the site look again" (asks before replacing template font and button edits).

= 1.8.1 =
* Buttons on Area pages are now an exact copy of the site's buttons: font, size, colors, gradient, border, corners, shadow, padding and the hover effect.
* A second button style (for example an outline "Contact Us" button) is read and copied too.
* Site button copy now wins over Divi and Elementor module styles that use strong selectors.
* Design tab shows a live preview of the copied buttons. Sites measured by 1.8.0 are measured again automatically.

= 1.8.0 =
* Magnific images (default): every page gets its own new stock photos from Magnific, downloaded into
  the Media Library with the page's SEO file name (balayage-in-cary-nc-1.jpg) and alt text. Media
  Library photos fill any gap. Photos picked by hand for a service are still used.
* The agency Magnific key is built into the plugin (includes/agency-config.php), so nothing has to be
  typed on each site. A key saved on a site or SEOB_MAGNIFIC_KEY in wp-config.php wins over it.
* "Test Magnific" button and last-error notice on the AI & images tab.

= 1.7.0 =
* Same look as the site: the plugin reads the homepage at desktop, tablet and phone width and gives
  Area pages (and the hub page) the same heading and text fonts, sizes, weights, line spacing and
  button shape, on every screen size. Works with Divi, Elementor and the built-in template. Runs by
  itself, refreshes every 2 weeks, "Read the site again" on the Design tab. Font files the homepage
  uses are loaded on Area pages too.
* Phone menu: Areas We Serve opens and closes with a tap (location, then its services) instead of
  showing every item open. Divi desktop shows 5 or more locations as a mega menu (columns).

= 1.6.0 =
* Automatic menu: "Areas We Serve" > Locations > Services is added to the site's live header menu by
  itself as soon as Area pages are published, and updates itself the moment pages are published or
  removed (no button, no waiting for cron). Sites that already have published pages get it once
  automatically after the update.
* It goes before "Contact" the first time; if you move it in Appearance > Menus it stays there.
* "Remove from menu" turns the automatic menu off until "Add to menu" is pressed again.

= 1.5.0 =
* Any AI: choose Claude, OpenAI, Google Gemini, DeepSeek, OpenRouter (hundreds of models), Groq, Mistral,
  xAI Grok, or "Other / custom" for any OpenAI-compatible API (paste its address).
* Each provider keeps its own encrypted key, so you can switch any time without typing keys again.
* "Show my models" lists the models your key can use. Leave the model empty and the plugin picks a good
  one itself, and picks another automatically if a model name stops working.
* Requests adjust themselves when a provider does not accept a setting (JSON mode, token limit).

= 2.2.0 =
* Built-in SEMrush Live API Engine & Default Agency PAT Key:
  - Default Agency PAT Key configured (`includes/agency-config.php`), eliminating manual key entry across all agency sites.
  - Live SEMrush v4 API integration (`https://api.semrush.com/apis/v4/keywords/v1/metrics`) with `Authorization: Apikey` header.
  - Zero-downtime graceful fallback: If SEMrush returns 403 (needs active API units / permissions) or network error, plugin automatically falls back to live Google Suggest engine with zero interruption.
  - 1-Click "Test Connection" status checker in Keyword Research admin panel.
  - Data Source badges (`SEMrush` or `Live Google`) displayed per keyword in results table and CSV exports.

= 2.1.0 =
* Autonomous Keyword Research & Search Volume Engine (In-Plugin SEMrush Alternative):
  - Real-time live Google Suggest API integration across 5 intent seeds (direct, buyer modifiers, question modifiers, transactional keywords, location variations).
  - Algorithmic monthly search volume calculation with local demographic multipliers and city tier adjustments.
  - Keyword Difficulty (KD% 0-100) scoring based on search phrase length, commercial modifier presence, and competition.
  - Search Intent classification badges (Commercial, Transactional, Informational, Local).
  - Estimated CPC calculation for PPC benchmarking.
  - 1-Click transfer to Area Pages generator and Keyword Adder.
  - CSV export for reporting and client presentations.

= 2.0.0 =
* Full SEO Pilot Integration:
  - Pillar 1: SEMrush CSV Upload & Intelligent Clustering with auto-fill into Area Pages.
  - Pillar 2: Calibrated AI Copywriter (50-60 char title, 150-158 char meta description, exact primary keyword in first 10% of content).
  - Pillar 3: Deterministic Rank Math 85+ Postmeta Bridge with 10-point mathematical scoring rubric.
  - Pillar 4: Divi Blurb, Gallery & Image Alt Automation (Tier 1 shortcode attribute injection + Tier 2 runtime filter at priority 999).
  - Pillar 5: Side-by-Side Review & Approval Interface with 1-click batch publishing and instant rollback.
  - Pillar 6: Automated 1-Click Rollback Snapshot Manager (`_seob_backup_snapshot`).
* Rich JSON-LD Schema Markup Engine:
  - LocalBusiness / Organization Schema with address, geo coordinates, telephone, openingHours, priceRange.
  - Service Schema with provider reference and areaServed.
  - FAQPage Schema auto-extracted from ACF repeater fields and Divi accordion modules.
  - BreadcrumbList and WebPage schema.
* Core Pages Optimizer Module:
  - Audit and optimize Home, About, Services pages to Rank Math 85+ with strict heading hierarchy and snapshots.

= 1.4.0 =
* Images: every page now gets its own photos automatically. Each location page of a service gets a
  different set of matching photos from the Media Library (and Pexels if a key is saved). Logos, badges,
  team headshots and photos of other services are left out; general shop photos are used only when a
  service has too few photos.
* SEO image names (on by default): each page gets its own copy of its photos named after the page
  (balayage-in-cary-nc-1.jpg) with the page's alt text, like renaming and uploading by hand.
* New report button "Different images on every page" fixes pages that were made before (no AI, text unchanged).
* Phone number and contact page are read from the site and filled in the form automatically.

= 1.3.1 =
* Much faster: several pages are written at the same time (default 4, AI & images tab), the AI is
  asked again only for real problems (not for a few words over or under), and "Fast" writing speed
  is the default.
* Divi: the Areas We Serve template now uses the site's global Theme Builder header and footer
  (existing templates are fixed automatically). Layout fonts follow the site's Divi heading and body fonts.

= 1.3.0 =
* Setup installs the SCO layout in Divi or Elementor Theme Builder automatically.
* Menu builder and Areas We Serve hub page with drag-and-drop order.

= 1.2.2 =
* The default Area page design is now the team's SCO layout (brand color banner, pill Call and Contact buttons, brand color middle section, white cards on grey, brand color FAQ bars, image call to action).
* Second brand color setting. "Start from a design" buttons (SCO or Modern) in the layout editor.

= 1.2.1 =
* Brand color shows only the real site colors (most used on the homepage), max 10.
* Click any section in the layout preview to edit it. Clearer: one layout for all pages.

= 1.2.0 =
* Page layout editor with live preview, export and import.
* One-click setup that imports ACF fields, post type and taxonomy into ACF.
* Automatic images from the Media Library, optional Pexels.

= 1.1.1 =
* Works with the latest Claude and OpenAI models (no temperature, thinking turned off, bigger token budget).
* One AI call per request so hosts that stop long requests do not break jobs; pages finish even if the tab is closed.
* Job pauses itself when 3 pages in a row fail with the same error.
* Phone numbers like 814.834.1700 are kept whole.
* Better brand color detection on Divi sites.

= 1.1.0 =
* New Area Pages module.

= 1.0.0 =
* First release with the Keyword Adder module.
