# SEO Boster: Developer Guide

For developers and AI agents changing the code. Read `HANDOFF.md` first for the story and status.

Plugin slug `seo-boster`, main file `seo-boster.php`, text domain `seo-boster`, class prefix `SEOB_`, option and meta prefix `seob_` / `_seob_`. Requires WordPress 5.8+, PHP 7.4+. GPL-2.0-or-later. No build step: plain PHP, CSS and jQuery.

---

## 1. File map

```
seo-boster.php                     Bootstrap: constants, requires, activation/deactivation, one-time rewrite flush
uninstall.php                      Drops plugin tables, options, Keyword Adder post meta. Area pages (content) are kept.
readme.txt                         WordPress readme with user docs and changelog

includes/
  class-seob-plugin.php            Singleton; loads modules (filter 'seo_boster_modules'); honours 'seob_disabled_modules'
  class-seob-module.php            Abstract base for modules: id, name, description, icon, register, render_page, stats, settings
  class-seob-admin.php             Admin menu, dashboard (module cards), shared page layout helpers, notices
  class-seob-security.php          cap() (filter 'seo_boster_capability', default manage_options), verify() = capability + nonce, POST readers, CSV cell escaping
  class-seob-db.php                Keyword Adder table {prefix}seob_placements

modules/keyword-adder/
  class-seob-keyword-adder.php     Module: screens (Add, Review, Report), handlers, CSV export
  class-seob-keyword-matcher.php   Pure scoring of keyword vs pages (no WP calls, unit-testable)
  class-seob-sentence-writer.php   Sentence templates (filter 'seo_boster_sentence_templates')
  class-seob-content-injector.php  Adds stored lines to the_content (post meta _seob_block, _seob_position)

modules/area-pages/
  class-seob-area-pages.php        Module: tabs, all admin handlers and AJAX, screens (Create, Progress, Design, Setup, AI & images)
  class-seob-ap-schema.php         Post type 'areas-we-serve', taxonomy 'location', ACF field group (from data/acf-export.json + 3 extra fields), save_field()/get()
  class-seob-ap-ai.php             Claude/OpenAI client, encrypted key storage, JSON extraction, test()
  class-seob-ap-writer.php         Prompt (team rules), checks, hard vs soft problems, one-call attempt() with state, build() of final field values
  class-seob-ap-jobs.php           Job queue: tables, create, claim (parallel-safe), step, process_item, save_post, cron backup, bulk actions
  class-seob-ap-design.php         Design modes, brand color detection, accent/accent2, Divi + Elementor installers, auto_install, built-in template routing, FAQ schema
  class-seob-ap-layout.php         Built-in layout: definition, presets (SCO, Modern), sanitize, CSS variables, renderer
  class-seob-ap-setup.php          One-click: install/activate ACF, import field group + post type + taxonomy into ACF DB
  class-seob-ap-images.php         Media Library matching, Pexels download
  class-seob-ap-nav.php            Menu builder, hub page shortcode [seob_areas_we_serve], Menu & hub page screen
  templates/single-area.php        Built-in template (theme header/footer + SEOB_AP_Layout::render)
  data/acf-export.json             Team's ACF export (field group key group_69206005b8377)
  data/divi-layout.json            Team's SCO Divi layout (Divi export format, data key "1067")
  data/elementor-layout.json       Team's Elementor layout (Elementor template export)

assets/css/admin.css               All admin styles, scoped to .seob-wrap
assets/css/area-pages.css          Front end: built-in layout, SCO preset (.seob-preset-sco), hub page
assets/css/front.css               Keyword Adder lines
assets/js/admin.js                 Shared admin helpers (counter, confirm, copy, filter)
assets/js/area-pages-admin.js      Create form (image grid, media picker, auto-fill), progress runner (parallel workers), menu sorting
assets/js/layout-editor.js         Built-in layout editor (sections, styles, presets, live preview, click-to-edit)
```

## 2. Data storage

### Tables
| Table | Purpose |
|-------|---------|
| `{prefix}seob_placements` | Keyword Adder: one row per keyword placed (batch_id, post_id, keyword, sentence, line_no, status active/removed, meta_backup). |
| `{prefix}seob_ap_jobs` | Area Pages jobs: id, name, settings (JSON of the form), status running/paused/finished, total. |
| `{prefix}seob_ap_items` | One row per Service x Location page: status pending/working/done/warning/failed/skipped, post_id, keyword, attempts, message, updated_at. |

Schema versions in options `seob_db_version` and `seob_ap_db_version`; `maybe_upgrade()` runs dbDelta when they change.

### Options
| Option | Content |
|--------|---------|
| `seob_disabled_modules` | Module ids turned off on the dashboard |
| `seob_settings_{module}` | Module settings (Keyword Adder form defaults) |
| `seob_ap_ai` | provider, model, speed (fast/balanced), key (encrypted `v1:` AES-256-CBC + HMAC with the site's salts), last4 |
| `seob_ap_parallel` | Pages written at the same time (1 to 8, default 4) |
| `seob_ap_images` | Encrypted Pexels key |
| `seob_ap_design` | mode builtin/divi/elementor, accent, faq_schema, divi_template, divi_layout, el_template |
| `seob_ap_layout` | Built-in layout: global style + sections list |
| `seob_ap_nav` | Menu and hub settings: menu_id, label, group, loc_order, svc_order, items (menu item IDs we made), auto, hub_id, hub_title, hub_intro |
| `seob_ap_used_{job}` | Headings already used per service (uniqueness hints for the AI) |
| `seob_ap_state_{item}` | Temporary: AI answer and problems between the 1st and 2nd call for a page |
| `seob_divi_hf_fixed` | One-time flag: old Divi templates repaired with global header/footer |
| `seob_flush_rewrite` | One-time flag to flush permalinks after activation/setup |

Transients: `seob_ap_site_colors` (12 h), `seob_ap_form_{user}` (form draft), `seob_ap_layout_preview_{user}`, `seob_ap_err_{user}`, `seob_ap_test_{user}`, `seob_ap_setup_report_{user}`, `seob_ka_{user}` (Keyword Adder review).

### Post meta on Area pages
All ACF field names from the export (`page_heading`, `section_2_heading` ... `faq_10_description`, `section_7_*`) plus `section_5_heading`, `section_5_description`, `section_5_right_image`, `location`, `seo_title`, `meta_desc`, `_seob_ap_keyword`, `_seob_ap_service`, Yoast (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw`), Rank Math (`rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`). Image fields store attachment IDs (ACF image format). With ACF active, `update_field( field_key )` is used; without ACF, raw meta plus the `_fieldname => field_key` reference.

Other meta: nav menu items made by the plugin carry `_seob_ap_menu = 1`; Pexels downloads carry `_seob_pexels_id`.

## 3. Main flows

### 3.1 Create pages
1. `handle_create` (admin-post `seob_ap_create`): parses lists (`split_list`, `split_keywords`, `split_phones`), validates images (`wp_attachment_is_image` + `read_post`), optional auto-fill of empty image cells (`SEOB_AP_Images::suggest`), saves the form draft, creates a job (`SEOB_AP_Jobs::create`) with one item per Service x Location, schedules cron `seob_ap_run_jobs`.
2. Progress screen JS starts N workers (N = `seob_ap_parallel`). Each worker POSTs `wp_ajax_seob_ap_step` repeatedly.
3. `SEOB_AP_Jobs::step`: `claim()` resets items stuck in "working" for more than 240 s, then atomically claims one pending item (`UPDATE ... WHERE id = X AND status = 'pending'`, rows affected must be 1). No job-level lock; workers run in parallel safely.
4. `process_item`: skip if the page exists and "Skip" was chosen; else `SEOB_AP_Writer::attempt()` makes ONE AI call. If the answer has serious problems and fewer than 2 calls were made, the state is saved in `seob_ap_state_{item}` and the item goes back to pending (the next request makes the fix call). Otherwise `build()` and `save_post()`.
5. Errors: 400/401/403/404 and missing key stop retrying; the job pauses when the last 3 finished pages failed with the same message (`same_failures`). Other errors retry up to 3 attempts.
6. WP-Cron backup (`seob_ap_run_jobs`) processes running jobs for ~40 s per run and reschedules itself, so jobs continue with the tab closed.

### 3.2 AI request (`SEOB_AP_AI::complete`)
- Anthropic: `POST https://api.anthropic.com/v1/messages`, headers `x-api-key`, `anthropic-version: 2023-06-01`; body: model, max_tokens (16000 for pages), system, messages, `thinking: {type: disabled}`. **No `temperature`** (current models reject it). If a 400 mentions "thinking", it retries once without that field.
- OpenAI: `POST https://api.openai.com/v1/chat/completions`, `max_completion_tokens`, `response_format: {type: json_object}`.
- Models: Fast = `claude-haiku-5-5` / `gpt-4.1-mini`; Best quality = `claude-sonnet-5-5` / `gpt-4.1-mini`; a typed model name overrides. Empty answers report `stop_reason` and content block types in the error.

### 3.3 Checks and rules (`SEOB_AP_Writer`)
- Word ranges from the team prompt (`word_rules`), 15% tolerance. Location (city part) must be in section 2/3/4 headings and FAQ headings. Section 2 heading must not use professional/reliable/expert. No sentence repeated across fields. Meta description at most 160 characters (trimmed by code otherwise).
- `hard()` decides what is worth a second call: missing fields, location missing, banned words, repeated sentences, word counts below half the minimum. Everything else becomes a note ("Done, check notes").
- `clean()` fixes the characters that broke the old CSV import (non-breaking hyphen, nbsp, smart quotes, em/en dashes).
- Code-filled fields: post_title, page_heading, call/contact buttons, images (one image in the first row = all services; otherwise image N for service N; missing = empty), alt tags fallback, SEO title fallback, phones cycle by row.

### 3.4 Design modes (`SEOB_AP_Design`)
- `auto_install()` (run by Setup): Divi active, then `install_divi(auto_color_map('divi'))`; else Elementor Pro, then `install_elementor(...)`; else built-in. An existing published template is kept (Divi: `repair_divi_template()` only fills header/footer slots still at 0).
- **Divi install:** layout text from `data/divi-layout.json`, `adapt()` swaps colors (`#947d65` to accent, `#5d7e81` and `#367b42` to accent2) and rewrites links to foreign domains (images from other client sites are dropped), fonts `Chivo` and `Roboto Flex` become `--et_global_heading_font` / `--et_global_body_font`. Creates `et_pb_layout` (Divi Library backup), `et_body_layout` (content), `et_template` (meta `_et_enabled`, `_et_default = 0`, `_et_body_layout_id`, header/footer IDs copied from the default template, `_et_use_on = singular:post_type:areas-we-serve:all`), adds the template ID to the live `et_theme_builder` post meta `_et_template`, clears Divi static resources.
- **Elementor install:** `elementor_library` post type `single-post`, `_elementor_data`, conditions `include/singular/areas-we-serve` via Elementor Pro's conditions manager (fallback: `_elementor_conditions` + option `elementor_pro_theme_builder_conditions`). Static buttons in the layout are linked to ACF fields with `acf-url` dynamic tags, and their old static links are cleared.
- **Built-in:** `template_include` returns `templates/single-area.php` when mode is builtin; CSS variables from `SEOB_AP_Layout::css_vars()`. Live preview uses `?seob_preview={nonce}` and the transient `seob_ap_layout_preview_{user}`; the admin bar is hidden in previews.
- **Brand colors:** `site_colors()` = the 6 most used non-grey hex colors in the homepage HTML (Divi/Elementor default blues excluded), then Divi options / Elementor kit / theme palette, max 10, cached 12 h. `accent()` picks the first strong color with luminance under 0.6; `accent2()` the next color that is dark enough for white text and clearly different, else a 30% darker accent.

### 3.5 Setup (`SEOB_AP_Setup`)
Two requests on purpose: `handle_setup` installs/activates ACF (Plugin_Upgrader from downloads.wordpress.org), then redirects to `handle_setup2` (new request, ACF loaded) which imports with `acf_import_field_group` and `acf_import_internal_post_type` (skips items already in the DB, adds missing extra fields to an existing group), then runs `auto_install()`.

### 3.6 Menu and hub (`SEOB_AP_Nav`)
`tree()` groups published Area pages by location or service in the saved drag order. `build_menu()` deletes items it made before (IDs in settings + meta `_seob_ap_menu`), then adds parent (links to hub page if it exists) > group (custom link to hub anchor) > page items. Auto-sync: `transition_post_status` schedules `seob_ap_menu_sync` 30 s later. Hub page slug `service-areas`, content `[seob_areas_we_serve]`, Divi meta for full width and hidden title.

## 4. Security conventions (keep them)
- Every admin action: `SEOB_Security::verify( 'action_name' )` (capability + nonce). AJAX: `check_ajax_referer` + `can_use()`. Per-post actions also check `edit_post`/`publish_post`/`delete_post`.
- Read input only through `SEOB_Security::post_*` helpers; escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`); all SQL through `$wpdb->prepare`.
- Never trust IDs or keywords coming back from forms when a server copy exists (Keyword Adder reads keywords from the transient, not the form).
- Secrets: stored encrypted, never echoed; only sent to the official API hosts. Pexels downloads only from `images.pexels.com`.
- CSV export cells are escaped against spreadsheet formula injection.

## 5. Hooks for extending
Filters: `seo_boster_capability`, `seo_boster_modules`, `seo_boster_excluded_ids`, `seo_boster_sentence_templates`, `seo_boster_area_attempts` (default 2).
Actions: `seo_boster_keywords_applied( $batch_id, $post_ids )`, `seo_boster_area_page_saved( $post_id, $values, $job_settings )`.
New module: create `modules/<name>/class-seob-<name>.php` extending `SEOB_Module`, require it in `seo-boster.php`, add it in `SEOB_Plugin::core_modules()` (or use the `seo_boster_modules` filter from another plugin). It gets a menu page, a dashboard card and an on/off switch.

## 6. Local testing (how every version was tested)
Environment used: Linux, PHP 8.x CLI with sqlite3, gd; Node + Playwright for screenshots.

1. Get WordPress and the SQLite driver (git works when wordpress.org is blocked):
   `git clone --depth 1 --branch 6.7.1 https://github.com/WordPress/WordPress.git wp`
   `git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git sqlite`
   Copy `sqlite/packages/plugin-sqlite-database-integration` to `wp/wp-content/plugins/sqlite-database-integration`, create `wp/wp-content/db.php` from its `db.copy` (replace `{SQLITE_IMPLEMENTATION_FOLDER_PATH}` and `{SQLITE_PLUGIN}`).
2. Use `dev-tools/wp-config.sample.php` (SQLite, debug log, site URL http://127.0.0.1:8899). Run `php dev-tools/install-wordpress.php` (admin / testpass123, local only).
3. Symlink the plugin: `ln -s /path/to/seo-boster wp/wp-content/plugins/seo-boster`. Optional: ACF free from `https://github.com/AdvancedCustomFields/acf`.
4. Put `dev-tools/fake-ai.php` in `wp/wp-content/mu-plugins/`: it answers Anthropic calls with rule-following JSON and deliberately breaks the first answer per page (tests the fix path). Add `sleep(3);` inside it to test parallel speed.
5. Serve: `PHP_CLI_SERVER_WORKERS=6 php -S 127.0.0.1:8899` from the `wp` folder (workers are needed for parallel tests and for the homepage color fetch).
6. Drive it with curl (log in, read `_wpnonce` from pages, POST to `admin-post.php` / `admin-ajax.php`) or Playwright. `dev-tools/example-job-test.php` and `example-menu-test.php` show direct PHP tests.
7. Always: `for f in $(find . -name '*.php'); do php -l $f; done`, `node --check` on JS files, and check `wp/debug.log`.

What cannot be tested locally: Divi and Elementor Pro (commercial), real AI and Pexels APIs. Test those on Penn Pallet.

## 7. Release procedure
1. Bump the version in `seo-boster.php` (header `Version:` and `SEOB_VERSION`) and `readme.txt` (`Stable tag`), add a changelog entry. The version is also the cache-buster for CSS/JS.
2. Lint (section 6.7), zip the `seo-boster` folder (the zip must contain the `seo-boster/` folder at its root).
3. Install: WordPress > Plugins > Add New > Upload Plugin > choose zip > Install Now > **Replace current with uploaded**. Settings and data are kept.
4. Emergency small fix without upload: Plugins > Plugin File Editor (works on Penn Pallet) or the admin-ajax action `edit-theme-plugin-file` (needs the editor page nonce). It cannot create new files. Bump `SEOB_VERSION` there too so browsers load new CSS/JS.

## 8. Lessons learned (real problems hit during this project)
- New Claude models reject `temperature`, and spend the whole token budget on extended thinking unless it is disabled; the error looked like "empty answer".
- LiteSpeed hosting kills PHP when the browser disconnects; navigating away mid-request left items stuck and a job lock held for minutes. Fixed with `ignore_user_abort`, one AI call per request, item-level claiming and stale-item reset.
- `wp_nonce_url()` returns `&amp;`; never use it for a redirect URL (use `add_query_arg` with `wp_create_nonce`).
- List parsing that strips "1." numbering also stripped the area code of "814.834.1700"; phones have their own parser.
- Divi sites can hold hundreds of global colors (598 on Penn Pallet); reading the homepage's most used colors gives the real brand.
- A Divi Theme Builder template with header/footer layout ID 0 shows no header/footer on sites that use a global Theme Builder header; copy the default template's IDs.
- Security plugins on client sites hide `wp-login.php` (Penn Pallet uses `/ds-login`) and return 403 on `/wp-admin/` when logged out; that is not a password problem.
- The SCO layout's buttons are `dnxte_dual_button` (Divi Essential plugin).
