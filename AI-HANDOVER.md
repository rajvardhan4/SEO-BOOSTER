# AI Handover: SEO Boster (with SEO Pilot Integration, Rich Schema & SEMrush Live Engine)

**Project Name:** SEO Boster (with SEO Pilot Architecture, Rich Schema Engine, Autonomous Keyword Engine & SEMrush Live API)  
**Author / Plugin Name:** Rajvardhan Singh Rathore / SEO Boster  
**Last Updated:** 2026-11-20 11:40:00 IST (Asia/Kolkata)  
**Current Plugin Version:** **2.2.0** (`01-PLUGIN-INSTALL/seo-boster-v2.2.0.zip`)  
**Target Environment:** WordPress 6.0+ (PHP 7.4+), Divi 4+, Elementor Pro, ACF Pro, Rank Math SEO / Yoast SEO  

---

## 1. Context, User Preferences, and Communication Style

- **User Language & Tone:** Simple Roman Hindi / Hinglish using English technical terms. Short, direct, clear answers.
- **Punctuation Rule:** Do NOT use em dashes (—) or en dashes (–) in written replies. Use normal hyphens (-), colons, or parentheses.
- **Working Style & Philosophy:**
  - One-click and automatic by default.
  - Agency credentials pre-configured in `includes/agency-config.php` so client sites work out-of-the-box without manual typing.
  - Never let an API error stop the SEO workflow: if SEMrush returns 403 or runs out of units, seamless zero-downtime fallback to live Google Suggest engine.
  - All zip packaging MUST strictly use forward slashes (`/`) so Linux/WordPress servers extract seamlessly without "Plugin file does not exist" errors.
  - Never store raw secrets or passwords in public documents.

---

## 2. Requirements & Specification Tracking

| Req ID | Requirement Description | Source | Status |
|---|---|---|---|
| R001 | Keyword Adder: Score existing pages, place keywords in natural sentence via `the_content` filter without altering original post content. | SEO Boster v1.0.0 | Completed |
| R002 | Area Pages: Bulk generation of Service x Location landing pages into `areas-we-serve` CPT with ACF field population (61 fields). | SEO Boster v1.1.0 - 1.9.0 | Completed |
| R003 | 1-Click Site Setup: Auto-install ACF configuration, Divi Theme Builder template, Elementor template support. | SEO Boster v1.3.0 | Completed |
| R004 | Site Look & Brand Adaptation: Measure homepage typography and buttons, bake directly into Divi Theme Builder templates. | SEO Boster v1.7.0 - 1.8.5 | Completed |
| R005 | Stock Images & Dynamic Alt Tags: Magnific stock photo integration, Media Library matching, SEO file copies with localized alt text. | SEO Boster v1.8.0 - 1.8.4 | Completed |
| R006 | Dynamic Navigation & Hub Page: Multi-tier "Areas We Serve > Location > Services" dropdown and `/service-areas/` hub page. | SEO Boster v1.6.0 - 1.8.3 | Completed |
| R007 | SEMrush Bulk CSV Drop-In: 1-click query generator helper (`{Service} {City}`) and drag-and-drop CSV parser with clustering engine (Volume & KD matching). | SEO Pilot Spec (handoff.md) | Completed (v2.0.0) |
| R008 | Dual-Mode Page Architecture: Split UI into Tab 1 (Core & Service Pages Audit & Optimizer) and Tab 2 (Service Area Bulk Engine). | SEO Pilot Spec (handoff.md) | Completed (v2.0.0) |
| R009 | Rank Math 85+ Postmeta Injection: Deterministic rubric (title first 50%, desc first sentence, body first 10%, H2/H3 subheadings, keyword density 1.0-1.4%, 800-1200 words). | SEO Pilot Spec (handoff.md) | Completed (v2.0.0) |
| R010 | ACF Read-Before-Write & Non-Destructive Sync: Mode A (Smart Sync), Mode B (Score < 80 only), Mode C (Full Polish) with granular toggles. | SEO Pilot Spec (handoff.md) | Completed (v2.0.0) |
| R011 | Divi Blurb & Gallery Image Alt Fix: Shortcode parameter injection (`image_alt`) + runtime `the_content` output filter (priority 999). | SEO Pilot Spec (handoff.md) | Completed (v2.0.0) |
| R012 | Enterprise Safety & Rollback: Draft mode default, side-by-side Review & Approval Table, 1-click rollback snapshot (`_seob_backup_snapshot`). | SEO Pilot Spec (handoff.md) | Completed (v2.0.0) |
| R013 | Rich JSON-LD Schema Markup Engine on All Pages: LocalBusiness / Organization, Service schema, FAQPage schema (auto-extracted from ACF fields and Divi accordions), BreadcrumbList, WebPage schema with dedicated settings dashboard. | User Request (2026-11-20) | Completed (v2.0.0) |
| R014 | Autonomous Keyword Research & Search Volume Engine: In-plugin SEMrush alternative querying live Google Suggest API across 5 intent seeds; calculating realistic monthly Search Volume, KD% difficulty (0-100), Intent Badges, estimated CPC, with 1-click pipe to Area Pages and Keyword Adder, plus CSV export. | User Request (2026-11-20) | Completed (v2.1.0) |
| R015 | Built-in SEMrush Live API Engine & Default PAT Token: Embed agency SEMrush PAT token (`semrtkn-pat-...`) as default in `includes/agency-config.php`, live v4 API integration with 7-day metric cache, 1-click connection tester, and automatic zero-downtime fallback to live Google Suggest engine. | User Request (2026-11-20) | Completed (v2.2.0) |

---

## 3. Decisions & Architectural Blueprint

- **D001 (Modular Architecture):** Keep SEO Boster's clean OOP module system (`SEOB_Module`, `SEOB_Plugin`). Modules remain independent cards on dashboard.
- **D002 (Integration Strategy for SEO Pilot):** All 6 pillars integrated into core and area pages modules.
- **D003 (Rich Schema Engine):** Complete schema graph injected into `wp_head`.
- **D004 (SEMrush Live API & Agency PAT Token):**
  - Configured default token in `includes/agency-config.php` (`SEOB_DEFAULT_SEMRUSH_KEY`).
  - Precedence: DB option (`seob_semrush_api_key`) -> `wp-config.php` constant (`SEOB_SEMRUSH_KEY`) -> Agency default constant (`SEOB_DEFAULT_SEMRUSH_KEY`).
  - Native v4 API endpoint: `https://api.semrush.com/apis/v4/keywords/v1/metrics` with `Authorization: Apikey <KEY>`.
  - Zero-downtime fallback: If token returns 403 (needs active API units or permissions), plugin displays a clear status and seamlessly uses the live Google Suggest engine without breaking client workflows.
  - 1-Click "Test Connection" status checker in Keyword Research admin panel.
  - Data Source badges (`SEMrush` or `Live Google`) per keyword in results table and CSV exports.

---

## 4. Current State & Site Inventory

- **Git Repository:** `https://github.com/rajvardhan4/SEO-BOOSTER.git` (Branch `main` - Up to date, verified clean working tree).
- **Plugin Source:** `02-SOURCE-CODE/seo-boster/` (v2.2.0 - all 45 PHP files passed PHP 8.2 linting with 0 errors).
- **Installable Zip:** `01-PLUGIN-INSTALL/seo-boster-v2.2.0.zip` (208 KB, strict POSIX forward-slash entries, verified extraction).
- **Credentials:**
  - Magnific Agency Key: configured in `includes/agency-config.php`
  - SEMrush Agency PAT Token: configured in `includes/agency-config.php`
- **Test Sites Status:**
  - `stapaverkings.com`: Demo site (Divi + Divi Essential). 6 services x 12 locations generated. Target: Upload v2.2.0.
  - `lmgdemo2.com/maeve`: Maeve Salon (Divi + Rank Math).
  - `lmgdemo2.com/penn`: Penn Pallet (Divi). First test site (v1.3.x).
  - `inclegals.com`: Client SEO task (world-wide targeting update, 25 keywords checked).

---

## 5. Next Actions

1. User uploads `seo-boster-v2.2.0.zip` to WordPress (Plugins > Add New > Upload Plugin > Replace current with uploaded).
2. Go to **SEO Boster > Keyword Research**:
   - Check SEMrush API status card (shows "Agency Default Active").
   - Click "Test Connection" to test live API handshake.
   - Run keyword discovery: returns live volume, KD%, intent, CPC, and data source badges.
