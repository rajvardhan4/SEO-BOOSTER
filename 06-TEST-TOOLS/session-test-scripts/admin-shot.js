const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1400, height: 900 } });
  const errs=[]; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type()==='error') errs.push(m.text()); });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit'); await p.waitForLoadState('networkidle');
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages', { waitUntil: 'networkidle' });
  await p.fill('#seob-services', 'AC Repair\nAC Installation\nHeat Pump Services');
  await p.fill('#seob-locations', 'Beverly Hills, FL\nCitrus Springs, FL\nDunnellon, FL\nHomosassa, FL');
  await p.click('.seob-img-pick >> nth=0'); await p.waitForTimeout(1500);
  const modal = await p.$('.media-modal'); console.log('media modal opened:', !!modal);
  if (modal) { const br = await p.$('#menu-item-browse'); if (br) await br.click(); await p.waitForSelector('.attachments .attachment', {timeout: 15000}); await p.click('.attachments .attachment >> nth=0'); await p.click('.media-button-select'); await p.waitForTimeout(500); }
  await p.click('.seob-img-all >> nth=0'); await p.waitForTimeout(300);
  console.log('total text:', await p.textContent('#seob-total'));
  console.log('filled image inputs:', await p.$$eval('#seob-image-grid input', els => els.filter(e => e.value).map(e => e.name + '=' + e.value)));
  await p.screenshot({ path: process.env.T + '/admin-gen.png', fullPage: true });
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=jobs&job=1', { waitUntil: 'networkidle' });
  await p.screenshot({ path: process.env.T + '/admin-job.png', fullPage: true });
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=design', { waitUntil: 'networkidle' });
  await p.screenshot({ path: process.env.T + '/admin-design.png', fullPage: true });
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster', { waitUntil: 'networkidle' });
  await p.screenshot({ path: process.env.T + '/admin-dash.png', fullPage: true });
  console.log('JS errors:', errs);
  await b.close();
})();
