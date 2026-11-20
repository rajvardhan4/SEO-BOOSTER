const { chromium } = require('playwright');
(async () => { const b = await chromium.launch(); const q = await b.newPage({viewport:{width:1280,height:900}}); await q.goto(process.argv[2]);
 console.log(await q.evaluate(()=>[...document.querySelectorAll('p')].slice(0,4).map(e=>e.className+' '+getComputedStyle(e).fontSize+' in '+(e.closest('.seob-area')?'area':'other')).join('\n')));
 console.log(await q.evaluate(()=>document.body.className.includes('seob-match'), ), (await q.content()).match(/<style id="seob-site-style">[^<]{0,400}/)?.[0]); await b.close(); })();
