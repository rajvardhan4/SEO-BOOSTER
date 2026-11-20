const { chromium } = require('playwright');
(async () => { const b = await chromium.launch(); const q = await b.newPage({viewport:{width:390,height:800}}); await q.goto('file://'+process.cwd()+'/mm.html');
 const vis = async t => await q.isVisible(`text=${t}`);
 console.log('start: Cary', await vis('Cary, NC'), 'Balayage', await vis('Balayage'));
 await q.click('text=Areas We Serve'); console.log('tap AWS: Cary', await vis('Cary, NC'), 'Balayage', await vis('Balayage'));
 await q.click('text=Cary, NC'); console.log('tap Cary: Balayage', await vis('Balayage'));
 await q.click('text=Cary, NC'); console.log('tap Cary again: Balayage', await vis('Balayage'));
 await b.close(); })();
