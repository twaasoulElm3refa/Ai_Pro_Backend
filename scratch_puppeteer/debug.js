const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  
  // Intercept console logs to surface them
  page.on('console', msg => {
    if (msg.text().includes('[TREND DEBUG]')) {
      console.log('BROWSER LOG:', msg.text());
    }
  });
  page.on('pageerror', err => console.log('BROWSER ERROR:', err.toString()));

  // Go to the production site
  await page.goto('https://pro.aiarabic.com/ar', { waitUntil: 'networkidle2' });

  // Inject the debugging function
  await page.evaluate(() => {
    window.debugTrendSection = () => {
      const section = document.querySelector('.home-trends-section');
      const vueApp = document.querySelector('[data-v-app]'); // or #app
      
      let trendToolsLoading = 'unknown';
      let trendToolsLength = 'unknown';
      let trendCardsPerView = 'unknown';
      let trendPageCount = 'unknown';
      
      // If we can't get Vue internals, we just report DOM metrics
      const getStyles = (el) => {
        if (!el) return null;
        const comp = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        return {
          exists: true,
          display: comp.display,
          visibility: comp.visibility,
          opacity: comp.opacity,
          width: rect.width,
          height: rect.height,
          overflow: comp.overflow,
          classList: Array.from(el.classList).join(' ')
        };
      };

      const parentStyles = (el) => {
        let parent = el ? el.parentElement : null;
        let pStyles = [];
        while (parent && parent.tagName !== 'BODY') {
           const c = window.getComputedStyle(parent);
           pStyles.push({
             tag: parent.tagName,
             class: parent.className,
             display: c.display,
             overflow: c.overflow,
             height: parent.getBoundingClientRect().height
           });
           parent = parent.parentElement;
        }
        return pStyles;
      };

      return {
        width: window.innerWidth,
        sectionDOM: getStyles(section),
        parents: getStyles(section) ? parentStyles(section) : null,
      };
    };
  });

  const viewports = [1920, 1600, 1440, 1366, 1280, 1200, 1024, 900, 768];
  
  for (const w of viewports) {
    await page.setViewport({ width: w, height: 1080 });
    // wait a moment for resize handlers
    await new Promise(r => setTimeout(r, 1000));
    
    const debugInfo = await page.evaluate(() => window.debugTrendSection());
    console.log(`\n\n=== WIDTH: ${w} ===`);
    console.log(JSON.stringify(debugInfo, null, 2));
  }

  await browser.close();
})();
