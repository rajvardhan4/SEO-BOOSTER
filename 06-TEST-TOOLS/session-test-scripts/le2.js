const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1500, height: 950 } });
  const errs=[]; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit'); await p.waitForLoadState('networkidle');
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=layout', { waitUntil: 'networkidle' });
  await p.waitForTimeout(3000);
  console.log('accent swatches:', await p.$$eval('#seob-le-global .seob-le-field:first-child .seob-swatch', e => e.length));
  const f = p.frame({ url: /seob_preview/ }); await f.waitForSelector('[data-seob-sec]');
  console.log('admin bar in preview:', !!(await f.$('#wpadminbar')));
  await f.click('.seob-sec-faq');
  await p.waitForTimeout(500);
  console.log('opened in sidebar:', await p.$eval('#seob-le-list li.is-open .seob-le-title', e => e.textContent));
  await f.hover('.seob-sec-cta'); await p.waitForTimeout(300);
  await p.screenshot({ path: process.env.T + '/le2.png' });
  console.log('JS errors:', errs);
  await b.close();
})();
