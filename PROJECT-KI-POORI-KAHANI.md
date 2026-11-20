# SEO Boster: project ki poori kahani

Shuru se 20 November 2026 tak. Technical detail ke liye `03-DOCS/HANDOFF.md` (section 4: har version) aur `03-DOCS/DEVELOPER-GUIDE.md` dekho.

---

## 1. Shuruaat: inclegals.com ka SEO kaam

Pehla kaam ek client site ka tha: **inclegals.com** (Divi, Yoast). Client chahta tha ki site sirf "Nevada" target na kare (woh poori duniya mein kaam karte hain) aur 25 keywords add hon (aged corp, shelf corp, aged LLC, Nevada/WY corp aur LLC, limited liability company, "for sale" ke saath aur bina).

Kya hua (11 November 2026):
- 9 pages ke Yoast titles aur descriptions worldwide kiye.
- Focus keyphrases set kiye.
- 3 pages par FAQ accordion mein keywords wale sawaal add kiye. Saare 25 keywords page text mein check kiye.

Baaki: 6 pages ke H1 mein abhi bhi "in Nevada" hai (aapse poochna tha), aur client ke Google Doc mein comment karna hai.

## 2. Idea: ek plugin jo yeh sab automatic kare

Isi kaam se idea aaya: **SEO Boster** naam ka WordPress plugin (author: Rajvardhan Singh Rathore), modules mein bana hua.

**Module 1: Keyword Adder (v1.0.0).** Keywords paste karo, plugin har page ko score karta hai (title, URL, Yoast/Rank Math keyphrase, text, synonyms jaise corp = corporation), sabse sahi page chunta hai, natural line likhta hai, aap review karte ho, phir add hota hai. Page ka asli content kabhi nahi badalta. Report mein exact line ka link, undo, CSV.

## 3. Bada kaam: Areas We Serve pages (Module 2: Area Pages)

**Purana manual tareeka (SEO team ka):** ACF install, ACF export import, CSV importer plugin, images upload karke URLs copy, ChatGPT prompt mein sab paste karke CSV banana, CSV import, Divi Theme Builder mein SCO layout import karke branding theek karna. Har client ke liye ghante lagte the.

**Aapka goal:** sab kuch automatic aur one click. Design ek jagah se edit ho. ACF fields bilkul purane jaise. Speed. Har site ka data alag.

Versions ke saath kya bana:

| Version | Kya hua (simple shabdon mein) |
|---|---|
| 1.1.0 | Area Pages module: form, AI writer, job queue, ACF fields, Divi/Elementor installer |
| 1.1.1 | Pehle live test ke fixes (Claude ka temperature bug, phone number katna) |
| 1.2.x | Plugin ke andar layout editor, ek click ACF setup, automatic images, site ke brand colors |
| 1.3.0 | Setup khud builder pehchanta hai aur SCO layout Theme Builder mein daalta hai. Menu builder aur hub page |
| 1.3.1 | Speed: 4 pages ek saath likhte hain. Site ka global header/footer. Divi ke global fonts |
| 1.4.0 | Har page par alag photos automatic, SEO file names ke saath (SEO team pehle haath se lagati thi) |
| 1.5.0 | Koi bhi AI provider ki API key |
| 1.6.0 | Menu automatic: live header menu mein "Areas We Serve > Location > Services" |
| 1.7.0 | Site jaisa look: plugin homepage padhkar fonts aur sizes copy karta hai |
| 1.8.0 | Magnific se photos, agency key plugin mein built-in |
| 1.8.1 | Buttons ki exact copy (colors, hover, shadow) |
| 1.8.2 | Design sirf Theme Builder se. Site ka look template ke andar likha jaata hai. "Advanced layout editor" builder sites se hataya |
| 1.8.3 | Demo site par menu ka dropdown bigad raha tha (12 locations ek doosre par). Fix: saaf list, services side mein |
| 1.8.4 | "Auto-fill images" ab har cell bharta hai, haath se kuch nahi |
| 1.8.5 | Demo site par buttons alag dikh rahe the, white text halke color par tha, phone par Contact button chhupa tha, FAQ chhota tha. Sab fix |
| 1.9.0 | Aapka naya Theme Builder layout (5 sections). Har site ke colors aur typography automatic. Image grid mein sirf woh photos jo template use karta hai |
| 2.0.0 | SEO Pilot ke saare features aur Rich Schema: Har page par schema (LocalBusiness, Service, FAQ, Breadcrumbs), Core Pages optimizer (Home, About, Services), SEMrush CSV drop-in aur keyword clustering, Rank Math 85+ formula, Divi Blurb alt fix (filter + shortcode), Review & Approval table, 1-Click Rollback snapshots |

## 4. Sites jahan test hua

- **Penn Pallet** (lmgdemo2.com/penn, Divi): pehla test site (v1.3.x). Yahan bugs mile aur fix hue.
- **Maeve Salon** (lmgdemo2.com/maeve, Divi, Rank Math): 1.3.1 install hua, setup done. Naye versions abhi upload karne hain.
- **Saint Augustine Paver Kings** (stapaverkings.com, Divi + Divi Essential): demo site. 6 services x 12 locations ke pages bane. Isi site par menu, buttons aur responsive ki problems pakdi gayi aur 1.8.3 se 1.9.0 mein fix hui.

## 5. Aapke important faisle (hamesha follow karne hain)

- Sab kuch **one click** aur **automatic**.
- **Design ek jagah:** Divi/Elementor sites par sirf Theme Builder. Plugin ka layout editor sirf un sites par jahan builder nahi hai.
- **ACF bilkul purane import jaisa** (same field names, keys).
- Jis site par plugin install ho, **wahi ke colors, fonts, heading sizes aur buttons**.
- **Har screen par responsive:** desktop, laptop, tablet, phone.
- **Speed** chhoti details se zyada zaroori.
- **Har site ka data alag.**
- **AI credits:** bade jobs se pehle poochna. Kuch bhi delete karne se pehle poochna.
- **Passwords:** AI kabhi password type nahi karega, aap khud login karte ho.
- **Reply:** Hinglish mein, chhota aur saaf, em dash ya en dash nahi.

## 6. Ek page kaise banta hai (andar se)

1. Har Service x Location ke liye plugin AI ko ek baar bolta hai ki saare text fields JSON mein do (SEO team ke prompt ke rules ke saath).
2. Plugin check karta hai: word count, heading mein location, title case, mana kiye gaye shabd, repeat sentences, meta length. Badi galti ho tabhi dobara AI ko bolta hai (max 2 baar).
3. Fixed cheezein code bharta hai: title "{Service} in {Location}", heading "Best {Service} in {Location}", phone `tel:+1...`, contact link, images, alt tags.
4. ACF fields, Yoast/Rank Math title, description, focus keyword, Location term, featured image save hote hain. FAQ schema har page par.
5. Design Theme Builder template se aata hai, jo saare Area pages par lagta hai.

## 7. Aage kaise kaam karna hai

`03-DOCS/HANDOFF.md` section 7 mein next steps hain. Har change ke baad: version badhao, `readme.txt` changelog, PHP lint, local test (`06-TEST-TOOLS/`), naya zip, aur `HANDOFF.md` update.
