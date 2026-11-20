const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1500, height: 950 } });
  const errs=[]; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit'); await p.waitForLoadState('networkidle');
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=layout', { waitUntil: 'networkidle' });
  await p.waitForTimeout(2500);
  console.log('sections:', await p.$$eval('#seob-le-list .seob-le-title', e => e.map(x => x.textContent)));
  // open Image+text Section 3, hide image, change side; hide boxes section; set accent
  await p.click('#seob-le-list li:nth-child(3) .seob-le-title');
  await p.uncheck('#seob-le-list li:nth-child(3) .seob-le-body input[type=checkbox] >> nth=0');
  await p.click('#seob-le-list li:nth-child(5) .seob-le-icon >> nth=0');
  await p.evaluate(() => { const c=document.querySelector('#seob-le-global input[type=color]'); c.value='#c8232b'; c.dispatchEvent(new Event('input',{bubbles:true})); });
  // add custom text section
  await p.selectOption('#seob-le-add-type', 'text'); await p.click('#seob-le-add');
  await p.fill('#seob-le-list li:last-child input.seob-input', 'Why {city} chooses {business}');
  await p.fill('#seob-le-list li:last-child textarea', 'We serve <strong>{city}</strong> every day.');
  await p.waitForTimeout(2500);
  console.log('status before save:', await p.textContent('#seob-le-status'));
  const frameSrc = await p.getAttribute('#seob-le-frame','src'); console.log('preview src has token:', /seob_preview=/.test(frameSrc));
  const f = p.frame({ url: /seob_preview/ }); 
  if (f) { await f.waitForLoadState(); console.log('preview: boxes hidden:', !(await f.$('.seob-sec-boxes')), '| custom text:', await f.$eval('.seob-sec-text h2', e=>e.textContent).catch(()=>null), '| accent:', await f.evaluate(()=>getComputedStyle(document.querySelector('.seob-area')).getPropertyValue('--seob-accent'))); }
  await p.screenshot({ path: process.env.T + '/le-edit.png' });
  // live page should still be the OLD layout before save
  const p2 = await b.newPage(); await p2.goto('http://127.0.0.1:8899/areas-we-serve/ac-repair-in-beverly-hills-fl/'); console.log('live before save has boxes:', !!(await p2.$('.seob-sec-boxes')));
  p.on('dialog', d => d.accept());
  await p.click('#seob-le-save'); await p.waitForTimeout(2000);
  console.log('status after save:', await p.textContent('#seob-le-status'));
  await p2.reload(); console.log('live after save: boxes hidden:', !(await p2.$('.seob-sec-boxes')), '| s3 image hidden:', !(await p2.$('.seob-sec-split:nth-of-type(3) img')), '| text:', await p2.$eval('.seob-sec-text', e=>e.innerText).catch(()=>null));
  await p2.setViewportSize({ width: 1280, height: 900 }); await p2.screenshot({ path: process.env.T + '/le-live.png', fullPage: true });
  console.log('JS errors:', errs);
  await b.close();
})();
