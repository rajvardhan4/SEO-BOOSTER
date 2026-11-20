const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1400,height:1000} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages');
  await p.fill('#seob-services', 'Roof Cleaning\nPressure Washing\nGutter Repair'); await p.dispatchEvent('#seob-services','input');
  // Hand-pick one cell (like the media picker does): id 141, not auto
  await p.evaluate(()=>{const c=document.querySelector('.seob-img-cell[data-field="section_3_right_image"][data-service="pressure washing"]'); c.querySelector('input.seob-img-id').value='141'; c.querySelector('input.seob-img-auto').value='';});
  for (const round of [1,2]) { await p.click('#seob-auto-images'); await p.waitForFunction(()=>!document.querySelector('#seob-auto-images').disabled); }
  const c = await p.evaluate(()=>{const c=document.querySelector('.seob-img-cell[data-field="section_3_right_image"][data-service="pressure washing"]'); return [c.querySelector('input.seob-img-id').value, c.querySelector('input.seob-img-auto').value];});
  const empty = await p.$$eval('.seob-img-cell', cs=>cs.filter(c=>!c.querySelector('input.seob-img-id').value).length);
  console.log('hand-picked cell after 2x autofill:', c, '| empty cells:', empty);
  await b.close();
})();
