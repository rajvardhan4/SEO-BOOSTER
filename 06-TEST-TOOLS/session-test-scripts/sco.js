const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  for (const [n,w] of [['desk',1280],['phone',390]]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 } });
    await p.goto('http://127.0.0.1:8899/areas-we-serve/ac-repair-in-beverly-hills-fl/', { waitUntil: 'networkidle' });
    await p.screenshot({ path: process.env.T + '/sco-' + n + '.png', fullPage: true });
    console.log(n, 'overflow:', await p.evaluate(() => document.documentElement.scrollWidth > innerWidth));
  }
  await b.close();
})();
