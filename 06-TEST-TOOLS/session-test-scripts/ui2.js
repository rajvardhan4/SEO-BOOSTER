const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1300,height:1200} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=menu');
  const card = p.locator('.seob-card', { hasText: 'Site menu (automatic)' });
  await card.screenshot({path:'menu-card.png'}); console.log('errors', errs.length, (await p.content()).match(/Fatal|Warning:/g));
  await b.close();
})();
