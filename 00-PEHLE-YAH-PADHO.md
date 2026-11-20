# SEO Boster: poora project (backup, 20 November 2026)

Is folder mein SEO Boster plugin ka **sab kuch** hai: install hone wala plugin, poora code, saare documents, design layouts, aapki original files, testing scripts, screenshots aur chat history.
Agar kisi aur (developer ya AI) se aage kaam karwana ho, toh yeh folder do. Agar woh theek se na kare, toh isi folder se dobara shuru kar sakte ho, kuch bhi khoya nahi jaayega.

**Latest version: 2.2.0** (`01-PLUGIN-INSTALL/seo-boster-v2.2.0.zip` - includes Built-in SEMrush Live API Engine & Default Agency Key, Autonomous Keyword Research, SEO Pilot 6 Pillars & Rich Schema on all pages)

---

## Folder mein kya kya hai

| Folder / file | Kya hai |
|---|---|
| `00-PEHLE-YAH-PADHO.md` | Yeh page |
| `PROJECT-KI-POORI-KAHANI.md` | Shuru se ab tak: kya maanga, kya banaya, har version mein kya badla, kaunsi problem kaise fix hui |
| `01-PLUGIN-INSTALL/` | Install hone wala plugin zip (WordPress > Plugins > Add New > Upload) |
| `02-SOURCE-CODE/seo-boster/` | Plugin ka poora code (zip ke andar wala hi, khula hua) |
| `03-DOCS/` | `HANDOFF.md` (status, sites, adhoore kaam), `DEVELOPER-GUIDE.md` (code kaise kaam karta hai), `00-START-HERE.md`, `PROMPT-FOR-NEW-AGENT.txt`, `CHANGELOG.txt` (har version) |
| `04-DESIGN-LAYOUTS/` | Divi layouts: **abhi wala (1.9.0, aapka "Theme Builder Layout(3).json")**, purana SCO layout (1.1 se 1.8.5 tak), Elementor layout |
| `05-ORIGINAL-INPUTS/` | Aapki team ki original files: ACF export, SCO layout, Elementor layout, demo CSV, purana ChatGPT prompt |
| `06-TEST-TOOLS/` | Local WordPress par testing ke scripts (nakli AI ke saath, AI ka kharcha nahi) |
| `07-SCREENSHOTS/` | Testing ke time liye gaye screenshots |
| `08-CHAT-HISTORY/` | Hamari baat-cheet ka record (padhne layak version, aur raw file) |
| `SEO-Boster-PROJECT.zip` | Isi poore folder ki ek zip copy (kisi ko bhejna ho toh) |

---

## Plugin kya karta hai (short mein)

WordPress admin mein **SEO Boster** menu. Do tools:

1. **Keyword Adder:** keywords paste karo, plugin har keyword ke liye sabse sahi page chunta hai aur ek natural line add karta hai. Report, undo, CSV.
2. **Area Pages:** SEO team ka form bharo (business, services, keywords, locations, phone). Har Service x Location ka page AI khud likhta hai, ACF fields mein (bilkul purane ACF import jaise), Theme Builder design ke saath.

Area Pages ki khaas baatein:
- **Ek click setup:** ACF install, field group import, Divi ya Elementor Theme Builder template install, site ka header aur footer.
- **Design ek jagah:** Divi/Elementor sites par sirf Theme Builder mein edit karo, saare pages badal jaate hain.
- **Site jaisa look:** plugin homepage padhkar fonts, heading sizes (desktop, tablet, phone), colors aur buttons (hover ke saath) template mein daal deta hai.
- **Images:** Magnific se automatic photos (agency key plugin mein), Media Library, Pexels. Har page par alag photos SEO file names ke saath. "Auto-fill" har cell bharta hai.
- **Menu:** "Areas We Serve > Location > Services" live menu mein khud banta hai, desktop aur phone dono par saaf.
- **Koi bhi AI:** Claude, OpenAI, Gemini, DeepSeek, OpenRouter, Groq, Mistral, xAI ya koi bhi OpenAI-compatible.
- **AI sirf page banate waqt use hota hai.** Pages ban jaane ke baad kisi API ki zarurat nahi.

---

## Kisi naye AI agent ko kaam dena ho

1. Is poore folder ki zip (`SEO-Boster-PROJECT.zip`) do, ya kam se kam: `03-DOCS/HANDOFF.md`, `03-DOCS/DEVELOPER-GUIDE.md`, `01-PLUGIN-INSTALL/seo-boster-v1.9.0.zip`.
2. Pehla message: `03-DOCS/PROMPT-FOR-NEW-AGENT.txt` ka text paste karo.
3. Phir apna kaam batao.

## Kisi developer ko dena ho

Folder do aur bolo pehle `03-DOCS/HANDOFF.md` phir `03-DOCS/DEVELOPER-GUIDE.md` padhe. Code `02-SOURCE-CODE/` mein hai.

## Agar naya agent kaam bigaad de

Yeh folder mat badlo, isko sirf backup ki tarah rakho. Wapas shuru karne ke liye `01-PLUGIN-INSTALL/seo-boster-v1.9.0.zip` site par upload karo (Replace current), aur aage ka kaam isi folder ke docs se shuru karo.

---

## Abhi kya baaki hai (detail `03-DOCS/HANDOFF.md` section 7 mein)

1. **stapaverkings.com (demo site):** 1.9.0 upload karna, Setup mein "Install a fresh copy of the design" (naya 5-section layout), Divi > Theme Builder mein purana template delete karna, phir desktop, laptop, tablet, phone par check.
2. **Maeve (lmgdemo2.com/maeve):** latest version upload, "Test Magnific" aur AI Test, chhota job.
3. **Penn Pallet (lmgdemo2.com/penn):** purana test site, poochna hai ki ab zarurat hai ya nahi.
4. **inclegals.com:** "in Nevada" wale H1 headings ke baare mein poochna, Google Doc mein comment.

## Zaroori: security

- **Passwords is folder mein nahi hain.** Client logins agency ke Google Docs mein hain.
- **Magnific API key** aapke kehne par plugin ke andar hai (`includes/agency-config.php`), isliye yeh folder aur zips **private** rakho. Key chat mein share hui thi, isliye Magnific mein ise badal dena (rotate) achha rahega. Nayi key us file mein daal ke naya version banana hota hai.
- AI API keys har WordPress site ke andar encrypted rehti hain, yahan nahi.
- `08-CHAT-HISTORY/raw-chat-log.jsonl` mein bhi Magnific key hai. Ise kisi ko mat bhejo.
