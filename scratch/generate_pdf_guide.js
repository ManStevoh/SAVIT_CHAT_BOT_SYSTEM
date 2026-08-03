
mconst { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

async function generatePDF() {
  const htmlContent = `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Meta WhatsApp API Setup Guide (Non-Developer Friendly)</title>
  <style>
    @page {
      size: A4;
      margin: 18mm 15mm 18mm 15mm;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      color: #1e293b;
      line-height: 1.5;
      font-size: 13px;
      background: #ffffff;
    }
    .header {
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 12px;
      margin-bottom: 20px;
    }
    .title {
      font-size: 22px;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 6px 0;
    }
    .subtitle {
      font-size: 13px;
      color: #64748b;
      margin: 0;
    }
    .badge-user {
      display: inline-block;
      background: #f1f5f9;
      color: #334155;
      font-weight: 600;
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 4px;
      margin-top: 6px;
      border: 1px solid #cbd5e1;
    }
    .callout {
      background: #f8fafc;
      border-left: 4px solid #475569;
      border: 1px solid #e2e8f0;
      border-left-width: 4px;
      padding: 12px 14px;
      border-radius: 6px;
      margin-bottom: 20px;
    }
    .callout-title {
      font-weight: 600;
      font-size: 13px;
      color: #0f172a;
      margin-bottom: 4px;
    }
    .callout p {
      margin: 0;
      font-size: 12px;
      color: #475569;
    }
    .step-card {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 14px 16px;
      margin-bottom: 16px;
      background: #ffffff;
      page-break-inside: avoid;
    }
    .step-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }
    .step-number {
      width: 24px;
      height: 24px;
      background: #0f172a;
      color: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 12px;
    }
    .step-title {
      font-size: 15px;
      font-weight: 600;
      color: #0f172a;
      margin: 0;
    }
    ol, ul {
      margin: 6px 0;
      padding-left: 20px;
    }
    li {
      margin-bottom: 5px;
      color: #334155;
    }
    .demystify {
      background: #f1f5f9;
      border-radius: 6px;
      padding: 10px 12px;
      margin-top: 8px;
      font-size: 12px;
    }
    .demystify-label {
      font-weight: 600;
      color: #334155;
    }
    code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      background: #e2e8f0;
      padding: 2px 5px;
      border-radius: 4px;
      font-size: 11px;
      color: #0f172a;
    }
    .footer {
      margin-top: 24px;
      border-top: 1px solid #e2e8f0;
      padding-top: 10px;
      text-align: center;
      font-size: 11px;
      color: #94a3b8;
    }
  </style>
</head>
<body>

  <div class="header">
    <div class="title">Meta Developer Portal Setup Guide</div>
    <div class="subtitle">A Simple Step-by-Step Walkthrough for Business Owners & Non-Technical Users</div>
    <div class="badge-user">Non-Technical Business Edition</div>
  </div>

  <div class="callout">
    <div class="callout-title">💡 Why do I need to do this?</div>
    <p>
      Meta (Facebook) requires business owners to create an API connection so your platform chatbot can safely send and receive WhatsApp messages on your behalf. You do NOT need to write any code—just copy and paste a few text values from Meta into your platform dashboard!
    </p>
  </div>

  <!-- Step 1 -->
  <div class="step-card">
    <div class="step-header">
      <div class="step-number">1</div>
      <div class="step-title">Log into Meta Developer Console</div>
    </div>
    <ol>
      <li>Open your web browser and navigate to <code>https://developers.facebook.com</code>.</li>
      <li>Click <strong>Log In</strong> in the top right corner and sign in with your main Facebook account.</li>
      <li>If prompted to register as a Meta Developer, click <strong>Get Started</strong>, accept the terms, and confirm your email.</li>
    </ol>
  </div>

  <!-- Step 2 -->
  <div class="step-card">
    <div class="step-header">
      <div class="step-number">2</div>
      <div class="step-title">Create Your WhatsApp App</div>
    </div>
    <ol>
      <li>Click <strong>My Apps</strong> in the top header menu.</li>
      <li>Click the green/primary <strong>Create App</strong> button.</li>
      <li>Select <strong>Other</strong> as your app goal and click <strong>Next</strong>.</li>
      <li>Select <strong>Business</strong> as your app type and click <strong>Next</strong>.</li>
      <li>Enter an <strong>App Display Name</strong> (e.g. <code>My Business WhatsApp</code>).</li>
      <li>Select your <strong>Meta Business Portfolio</strong> (Business Manager account).</li>
      <li>Click <strong>Create App</strong> and enter your Facebook password to confirm.</li>
    </ol>
  </div>

  <!-- Step 3 -->
  <div class="step-card">
    <div class="step-header">
      <div class="step-number">3</div>
      <div class="step-title">Add WhatsApp Product to Your App</div>
    </div>
    <ol>
      <li>On your app dashboard, scroll to <strong>Add products to your app</strong>.</li>
      <li>Find <strong>WhatsApp</strong> and click <strong>Set up</strong>.</li>
      <li>Select your Meta Business Portfolio if prompted and click <strong>Continue</strong>.</li>
    </ol>
  </div>

  <!-- Step 4 -->
  <div class="step-card">
    <div class="step-header">
      <div class="step-number">4</div>
      <div class="step-title">Copy Your Credentials to the Dashboard</div>
    </div>
    <p>In the left sidebar menu, click <strong>WhatsApp → API Setup</strong>:</p>
    <ol>
      <li><strong>Phone Number ID</strong>: Copy the long number listed under <em>Step 1: Select phone number</em> and paste it into your platform settings.</li>
      <li><strong>WhatsApp Business Account ID (WABA ID)</strong>: Copy the numeric ID shown under <em>WhatsApp Business Account ID</em> near the top of the page.</li>
      <li><strong>Permanent Access Token</strong>:
        <ul>
          <li>Go to Meta Business Manager (<code>https://business.facebook.com</code>) → <strong>Settings</strong> → <strong>Users</strong> → <strong>System Users</strong>.</li>
          <li>Click <strong>Add System User</strong>, set Role to <strong>Admin</strong>.</li>
          <li>Click <strong>Add Assets</strong>, select your App, and grant <strong>Full Control</strong>.</li>
          <li>Click <strong>Generate Token</strong>, check <code>whatsapp_business_messaging</code> and <code>whatsapp_business_management</code>, then copy the generated token.</li>
        </ul>
      </li>
    </ol>
    <div class="demystify">
      <span class="demystify-label">Demystified:</span> <strong>Phone Number ID</strong> is Meta's internal code for your phone number. <strong>WABA ID</strong> is your company's WhatsApp account identity code.
    </div>
  </div>

  <!-- Step 5 -->
  <div class="step-card">
    <div class="step-header">
      <div class="step-number">5</div>
      <div class="step-title">Copy Your Meta App Secret</div>
    </div>
    <ol>
      <li>In the left sidebar menu of Meta Developer Console, click <strong>App settings → Basic</strong>.</li>
      <li>Find <strong>App Secret</strong> and click <strong>Show</strong> (re-enter Facebook password if asked).</li>
      <li>Copy the string of letters and numbers and paste it into your platform dashboard.</li>
    </ol>
    <div class="demystify">
      <span class="demystify-label">Demystified:</span> <strong>App Secret</strong> is like a secure digital signature key that proves incoming messages truly came from your Meta account.
    </div>
  </div>

  <!-- Step 6 -->
  <div class="step-card">
    <div class="step-header">
      <div class="step-number">6</div>
      <div class="step-title">Configure Inbound Webhooks</div>
    </div>
    <ol>
      <li>In the left sidebar menu, click <strong>WhatsApp → Configuration</strong>.</li>
      <li>Under <strong>Webhooks</strong>, click <strong>Edit</strong>.</li>
      <li>In <strong>Callback URL</strong>, paste your platform Webhook URL (copied from your dashboard).</li>
      <li>In <strong>Verify Token</strong>, invent any custom secret passphrase (e.g. <code>my_secret_token_123</code>) and click <strong>Verify and Save</strong>.</li>
      <li>Paste that SAME passphrase into the <strong>Webhook Verify Token</strong> field in your platform dashboard.</li>
      <li>Under Webhook fields, click <strong>Manage</strong> and check <code>messages</code> subscription.</li>
    </ol>
  </div>

  <div class="footer">
    WhatsApp Business Automation • Platform Non-Developer Setup Documentation
  </div>

</body>
</html>
  `;

  const outputDir = path.join(__dirname, '..', 'LARAVEL_BACKEND', 'public', 'docs');
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  const pdfPath = path.join(outputDir, 'Meta_Developer_WhatsApp_Setup_Guide.pdf');

  console.log('Launching browser to generate PDF...');
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setContent(htmlContent, { waitUntil: 'load' });
  await page.pdf({
    path: pdfPath,
    format: 'A4',
    printBackground: true,
    margin: { top: '15mm', bottom: '15mm', left: '15mm', right: '15mm' },
  });
  await browser.close();

  const sizeKb = (fs.statSync(pdfPath).size / 1024).toFixed(2);
  console.log(`SUCCESS: Created PDF guide at:\n${pdfPath} (${sizeKb} KB)`);
}

generatePDF().catch((err) => {
  console.error('Error generating PDF:', err);
  process.exit(1);
});
