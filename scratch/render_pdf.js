const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

(async () => {
  const htmlPath = path.join(__dirname, 'Codebase_Onboarding_Guide.html');
  const pdfPath1 = path.join(__dirname, '..', 'docs', 'Codebase_Onboarding_Guide.pdf');
  const pdfPath2 = path.join(__dirname, '..', 'Codebase_Onboarding_Guide.pdf');

  console.log('Loading browser...');
  const browser = await chromium.launch();
  const page = await browser.newPage();

  console.log('Loading HTML file:', htmlPath);
  await page.goto(`file://${htmlPath}`, { waitUntil: 'networkidle' });

  console.log('Generating PDF...');
  await page.pdf({
    path: pdfPath1,
    format: 'A4',
    printBackground: true,
    margin: { top: '15mm', right: '15mm', bottom: '15mm', left: '15mm' }
  });

  // Also copy to root for easy user access
  fs.copyFileSync(pdfPath1, pdfPath2);

  await browser.close();
  console.log('PDF Successfully Generated at:');
  console.log(' -', pdfPath1);
  console.log(' -', pdfPath2);
})();
