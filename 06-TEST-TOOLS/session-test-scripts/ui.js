const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1280,height:1100} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab=settings');
  const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  console.log('help:', await p.textContent('#seob-keys-help'), '| base hidden:', await p.isHidden('#seob-base-wrap'));
  await p.selectOption('#seob-provider','custom');
  console.log('custom -> base hidden:', await p.isHidden('#seob-base-wrap'), '| key ph:', await p.getAttribute('#seob-key','placeholder'));
  await p.selectOption('#seob-provider','gemini');
  console.log('gemini help:', await p.textContent('#seob-keys-help'));
  await p.click('#seob-load-models'); await p.waitForTimeout(300);
  console.log('models msg (provider changed):', await p.textContent('#seob-models-msg'), 'errors:', errs.length);
  await p.selectOption('#seob-provider','anthropic');
  await p.locator('form.seob-card').first().screenshot({path:'ai-settings.png'});
  await b.close();
})();
