const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1300,height:1000} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=design');
  await p.waitForFunction(()=>/updated|Could not/.test(document.getElementById('seob-style-status')?.textContent||''),null,{timeout:90000});
  console.log('status:', await p.textContent('#seob-style-status'));
  console.log('summary:', (await p.$$eval('#seob-style-summary li', l=>l.map(x=>x.textContent))).join(' | '), 'errs', errs);
  const read = async (url,w) => { const q = await b.newPage({viewport:{width:w,height:900}}); await q.goto(url); const r = await q.evaluate(()=>{ const g=s=>{const e=[...document.querySelectorAll(s)].find(x=>x.getBoundingClientRect().height>0 && !x.closest('header,footer,nav,#wpadminbar')); if(!e) return '-'; const c=getComputedStyle(e); return c.fontFamily.split(',')[0]+' '+c.fontSize+' '+c.fontWeight+' lh'+c.lineHeight;}; return {h1:g('h1'),h2:g('h2'),p:g('p'),btn:g('.seob-area-btn, .wp-block-button__link, .wp-element-button')}; }); await q.close(); return r; };
  const area = process.argv[2];
  for (const w of [1280,390]) { console.log(w,'HOME', JSON.stringify(await read('http://127.0.0.1:8899/',w))); console.log(w,'AREA', JSON.stringify(await read(area,w))); }
  await b.close();
})();
