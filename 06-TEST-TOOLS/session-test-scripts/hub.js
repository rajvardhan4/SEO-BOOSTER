const { chromium } = require('playwright');
(async () => { const b = await chromium.launch();
 for (const [n,w] of [['desk',1280],['phone',390]]) { const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.goto('http://127.0.0.1:8899/service-areas/', { waitUntil: 'networkidle' });
  await p.screenshot({ path: process.env.T + '/hub-' + n + '.png', fullPage: true }); console.log(n, await p.evaluate(()=>[document.querySelectorAll('.seob-hub-card').length, document.documentElement.scrollWidth > innerWidth])); }
 await b.close(); })();
