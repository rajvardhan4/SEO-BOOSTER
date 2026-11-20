const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1300,height:1000} });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','testpass123'); await p.click('#wp-submit');
  for (const tab of ['layout','design']) {
    await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=seo-boster-area-pages&tab='+tab);
    const t = await p.textContent('body');
    console.log(process.argv[2], tab, /Fatal error|Warning:|Notice:|Deprecated:/.test(t)?'PHP ERROR':'ok',
      '| editor:', !!(await p.$('#seob-le')), '| advanced text:', t.includes('Advanced: use the SEO Boster layout editor'),
      '| copy-again btn:', t.includes('Copy the site look again'), '| checkboxes:', !!(await p.$('input[name=buttons]')));
    if (tab==='design') await p.screenshot({path:process.argv[3]+'/design-'+process.argv[2]+'.png', fullPage:false});
  }
  await b.close();
})();
