const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1300,height:1000} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=design');
  await p.waitForFunction(()=>/updated|Could not/.test(document.getElementById('seob-style-status')?.textContent||''),null,{timeout:120000});
  console.log('status:', await p.textContent('#seob-style-status'));
  await p.reload();
  console.log('summary:', (await p.$$eval('#seob-style-summary li', l=>l.map(x=>x.textContent))).join(' | '), 'errs', errs);
  const pv = await p.$('.seob-btn-preview'); if (pv) await pv.screenshot({path:process.argv[2]+'/pv.png'});
  const P=['fontFamily','fontSize','fontWeight','textTransform','letterSpacing','paddingLeft','paddingTop','borderTopLeftRadius','borderTopWidth','borderTopColor','backgroundColor','color','boxShadow'];
  const read = async (q, sel) => { const e = await q.$(sel); const pick = () => e.evaluate((el,P)=>{const c=getComputedStyle(el);return P.map(k=>c[k]).join(' ; ');},P);
    const base = await pick(); await e.hover(); await q.waitForTimeout(600); const hov = await pick(); await q.mouse.move(0,0); await q.waitForTimeout(600); return {base,hov}; };
  const home = await b.newPage({viewport:{width:1280,height:900}}); await home.goto('http://127.0.0.1:8899/');
  const area = await b.newPage({viewport:{width:1280,height:900}}); await area.goto('http://127.0.0.1:8899/areas-we-serve/roof-cleaning-in-hernando-fl/');
  const h1 = await read(home,'.et_pb_button:not(.ghost)'), a1 = await read(area,'.seob-area-btn:not(.is-ghost)');
  const h2 = await read(home,'.et_pb_button.ghost'), a2 = await read(area,'.seob-area-btn.is-ghost');
  for (const [n,h,a] of [['MAIN',h1,a1],['SECOND',h2,a2]]) for (const k of ['base','hov']) { console.log(n,k, h[k]===a[k]?'SAME':'DIFF'); if(h[k]!==a[k]) console.log('  home',h[k],'\n  area',a[k]); }
  await area.locator('.seob-area-btn').first().scrollIntoViewIfNeeded(); await area.screenshot({path:process.argv[2]+'/area-btn.png'});
  await b.close();
})();
