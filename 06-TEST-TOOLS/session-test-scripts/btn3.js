const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1300,height:1000} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=design');
  await p.waitForFunction(()=>/updated|Could not/.test(document.getElementById('seob-style-status')?.textContent||''),null,{timeout:120000});
  await p.reload();
  console.log('summary:', (await p.$$eval('#seob-style-summary li', l=>l.map(x=>x.textContent))).slice(-1)[0]);
  const a = await b.newPage({viewport:{width:1280,height:900}}); await a.goto('http://127.0.0.1:8899/areas-we-serve/roof-cleaning-in-hernando-fl/'); await a.waitForTimeout(2500);
  const r = await a.$$eval('.seob-area-btn', bs=>bs.map(x=>{const c=getComputedStyle(x);let p=x.parentElement,bg='';while(p){const pc=getComputedStyle(p);if(pc.backgroundColor!=='rgba(0, 0, 0, 0)'){bg=pc.backgroundColor;break;}p=p.parentElement;}return x.textContent.trim()+' | bg '+c.backgroundColor+' col '+c.color+' border '+c.borderTopColor+' | section '+bg+' | '+x.className}));
  r.forEach(x=>console.log(' ',x));
  await b.close();
})();
