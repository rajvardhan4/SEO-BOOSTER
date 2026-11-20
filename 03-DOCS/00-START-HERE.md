# START HERE

This folder is the complete handoff for the **SEO Boster** WordPress plugin project and the related client SEO work.
Anyone (a developer or an AI agent) can continue the work using only this folder.

## Read in this order

| # | File | What it is | Who needs it |
|---|------|------------|--------------|
| 1 | `00-START-HERE.md` | This page | Everyone |
| 2 | `HANDOFF.md` | The whole story: goal, history, current status of every site, open issues, next steps, user preferences | Everyone |
| 3 | `DEVELOPER-GUIDE.md` | How the code works, data storage, every flow, how to test locally, how to release, lessons learned | Developers and AI agents changing code |
| 4 | `PROMPT-FOR-NEW-AGENT.txt` | The first message to paste into a new AI agent | Whoever starts a new AI session |
| 5 | `seo-boster-source/` | The plugin source code (latest, v1.9.0) | Developers |
| 6 | `seo-boster-v1.9.0.zip` | The installable plugin (WordPress > Plugins > Add New > Upload) | Anyone installing it |
| 7 | `inputs/` | The team's original files the plugin was built from (ACF export, SCO Divi layout, Elementor layout, demo CSV, the old ChatGPT prompt) | Developers |
| 8 | `dev-tools/` | Scripts used to test the plugin on a local WordPress with a fake AI | Developers |

## Handing this to a new AI agent

1. Give the agent this whole `HANDOFF` folder (attach it, or connect the folder if the tool allows folders).
   If the tool only accepts a few files, give at least: `HANDOFF.md`, `DEVELOPER-GUIDE.md`, `seo-boster-v1.9.0.zip`.
2. Paste the text from `PROMPT-FOR-NEW-AGENT.txt` as the first message.
3. Then tell it the task you want done, for example "stapaverkings.com pe 1.9.0 lag gaya hai, ab responsive check karo".

## Handing this to a human developer

Give them the folder and ask them to read `HANDOFF.md` then `DEVELOPER-GUIDE.md`.
They will need a WordPress admin login for the site they work on (logins are NOT in this folder, see HANDOFF.md section 9).

## Short version (Hinglish)

- **Kya bana hai:** SEO Boster plugin. Isme 2 tool hain: Keyword Adder (keywords ko sahi page pe daalna) aur Area Pages (SEO team ka form bharo, Service x Location ke saare pages AI se khud bante hain, ACF fields, Theme Builder design, menu aur hub page ke saath).
- **Latest version:** 1.9.0 (`seo-boster-v1.9.0.zip`).
- **Demo site:** stapaverkings.com (Divi). Pehle Maeve aur Penn Pallet (lmgdemo2.com) pe test hua.
- **Sabse pehla baaki kaam:** stapaverkings.com pe v1.9.0 upload karke "Install a fresh copy of the design" se naya layout lagana, phir har screen size pe check karna (HANDOFF.md section 7).
