const puppeteer = require('puppeteer-core');

(async () => {
  const browser = await puppeteer.launch({ 
      executablePath: 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
      headless: 'new' 
  });
  const page = await browser.newPage();
  
  page.on('console', msg => {
    if (msg.text().includes('[TREND DEBUG]')) {
      console.log('BROWSER LOG:', msg.text());
    }
  });

  console.log("Navigating to mock site...");
  await page.goto('http://127.0.0.1:8081/mock.html', { waitUntil: 'networkidle2' });
  await page.waitForSelector('.tools-panel', { timeout: 10000 }).catch(e => console.log('Timeout waiting for .tools-panel'));

  await page.evaluate(() => {
    window.debugTrendSection = () => {
      const section = document.querySelector('.home-trends-section');
      
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
             visibility: c.visibility,
             opacity: c.opacity,
             overflow: c.overflow,
             height: parent.getBoundingClientRect().height,
             width: parent.getBoundingClientRect().width
           });
           parent = parent.parentElement;
        }
        return pStyles;
      };

      return {
        width: window.innerWidth,
        sectionDOM: getStyles(section),
        parents: getStyles(section) ? parentStyles(section) : null,
        panelHTML: document.querySelector('.tools-panel') ? document.querySelector('.tools-panel').innerHTML.substring(0, 1000) : 'NO PANEL'
      };
    };
  });

  const viewports = [1920, 1440, 1366, 1280, 1024, 900, 768];
  
  for (const w of viewports) {
    await page.setViewport({ width: w, height: 1080 });
    // Wait for Vue's resize handlers and re-renders
    await new Promise(r => setTimeout(r, 2000));
    
    const debugInfo = await page.evaluate(() => window.debugTrendSection());
    console.log(`\n\n=== WIDTH: ${w} ===`);
    console.log(JSON.stringify(debugInfo, null, 2));
  }

  await browser.close();
})();
