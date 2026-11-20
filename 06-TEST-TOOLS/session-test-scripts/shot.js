const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  for (const [name, w] of [['desk', 1280], ['phone', 390]]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 }, deviceScaleFactor: 1 });
    await p.goto('http://127.0.0.1:8899/areas-we-serve/ac-repair-in-beverly-hills-fl/', { waitUntil: 'networkidle' });
    await p.screenshot({ path: process.env.T + '/area-' + name + '.png', fullPage: true });
    const over = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    console.log(name, 'horizontal overflow:', over);
  }
  await b.close();
})();
