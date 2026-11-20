const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1400,height:1000} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages');
  await p.fill('#seob-services', 'Roof Cleaning\nPressure Washing\nGutter Repair\nSolar Panel Cleaning\nDeck Staining\nWindow Tinting');
  await p.dispatchEvent('#seob-services','input');
  const total = await p.$$eval('.seob-img-cell', c=>c.length);
  const t0=Date.now(); await p.click('#seob-auto-images');
  await p.waitForFunction(()=>!document.querySelector('#seob-auto-images').disabled,null,{timeout:180000});
  const empty = await p.$$eval('.seob-img-cell', cs=>cs.filter(c=>!c.querySelector('input.seob-img-id').value).length);
  const auto = await p.$$eval('input.seob-img-auto', cs=>cs.filter(c=>c.value==='1').length);
  const rows = await p.$$eval('#seob-image-grid tbody tr', rs=>rs.map(r=>[...r.querySelectorAll('input.seob-img-id')].map(i=>i.value).join(',')));
  console.log('cells',total,'empty',empty,'auto-flagged',auto,'secs',((Date.now()-t0)/1000).toFixed(1));
  console.log('status:', await p.textContent('#seob-img-status')); rows.forEach(r=>console.log('  ',r)); console.log('errs',errs);
  await p.locator('#seob-image-grid').screenshot({path:process.argv[2]+'/autofill.png'});
  await b.close();
})();
