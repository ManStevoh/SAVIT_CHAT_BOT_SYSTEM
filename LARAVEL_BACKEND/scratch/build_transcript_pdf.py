import os
import subprocess

html_content = """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Conversation Transcript & Technical QA Report</title>
    <style>
        @page {
            size: A4;
            margin: 20mm 15mm 20mm 15mm;
            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                font-family: system-ui, -apple-system, sans-serif;
                font-size: 9pt;
                color: #64748b;
            }
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            line-height: 1.6;
            font-size: 10.5pt;
            margin: 0;
            padding: 0;
        }

        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 20pt;
            color: #1e293b;
            margin: 0 0 8px 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header .meta {
            font-size: 9.5pt;
            color: #64748b;
            display: flex;
            gap: 20px;
        }

        .turn-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .turn-header {
            background: #e2e8f0;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 9.5pt;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .question-box {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 14px 18px;
            margin: 15px;
            border-radius: 4px;
        }

        .question-title {
            font-size: 9pt;
            font-weight: 700;
            color: #1d4ed8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .question-text {
            font-size: 11pt;
            font-weight: 600;
            color: #1e3a8a;
            margin: 0;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        }

        .response-box {
            padding: 10px 18px 18px 18px;
        }

        .response-title {
            font-size: 9pt;
            font-weight: 700;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        h2, h3, h4 {
            color: #0f172a;
            margin-top: 16px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        h3 { font-size: 13pt; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        h4 { font-size: 11pt; }

        p {
            margin-top: 0;
            margin-bottom: 12px;
        }

        ul, ol {
            margin-top: 0;
            margin-bottom: 12px;
            padding-left: 20px;
        }

        li {
            margin-bottom: 6px;
        }

        code {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            background: #f1f5f9;
            color: #0f172a;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 9.5pt;
            border: 1px solid #cbd5e1;
        }

        pre {
            background: #0f172a;
            color: #f8fafc;
            padding: 14px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 9pt;
            line-height: 1.45;
            margin-top: 10px;
            margin-bottom: 14px;
        }

        pre code {
            background: transparent;
            color: inherit;
            padding: 0;
            border: none;
        }

        hr {
            border: 0;
            height: 1px;
            background: #cbd5e1;
            margin: 18px 0;
        }

        .footer-note {
            margin-top: 30px;
            text-align: center;
            font-size: 8.5pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Conversation Transcript & Technical QA Report</h1>
        <div class="meta">
            <span><strong>System:</strong> Essem Chat AI Platform</span>
            <span><strong>Date:</strong> August 3, 2026</span>
            <span><strong>Format:</strong> Word-for-Word Verification</span>
        </div>
    </div>

    <!-- TURN 1 -->
    <div class="turn-card">
        <div class="turn-header">Turn 1 — User Inquiry & Architectural System Fix</div>
        <div class="question-box">
            <div class="question-title">User Question 1</div>
            <p class="question-text">what the last ai did was a stupid fix you are a better model do better</p>
        </div>
        <div class="response-box">
            <div class="response-title">Assistant Response 1</div>
            <p>I have resolved the root issue at the <strong>AI Agent System Prompt & Orchestrator</strong> level instead of relying on superficial string matching.</p>
            <hr>
            <h3>Root Cause & Proper Fix</h3>
            
            <h4>Why It Failed Before</h4>
            <p>The conversational bot is driven by an AI Agent (<code>CommerceAgentOrchestrator.php</code>). The AI Agent's system prompt lacked explicit rules for how to handle catalog requests, image tags, and persistent storefront links. As a result, when a user said <code>"no show me your catalog"</code>, the LLM fell back to generating generic text (<em>"If you'd like to proceed with an order, please let me know..."</em>) instead of invoking tools to display the catalog.</p>

            <h4>What Has Been Fixed</h4>
            <ol>
                <li><strong>AI Agent Persona Prompt (<code>CommerceAgentOrchestrator.php</code>)</strong>:
                    <ul>
                        <li>Injected explicit instructions into the core system prompt persona:
                            <ul>
                                <li><strong>Catalog Rule</strong>: Whenever a customer asks for the catalog (<code>"show me your catalog"</code>, <code>"products"</code>, <code>"what do you sell"</code>), the AI Agent is instructed to execute <code>get_catalog</code> / <code>process_order_message</code> immediately and present the numbered catalog.</li>
                                <li><strong>Image Rule</strong>: Whenever a customer asks for images of cart items or products (<em>"can I get an image of the item in my cart?"</em>), the AI Agent is instructed to use <code>[IMAGE_URL: &lt;url&gt; CAPTION: &lt;caption_text&gt;]</code> or call <code>process_order_message</code> with <code>"images"</code>. Broken raw markdown (<code>![alt](url)</code>) is explicitly banned.</li>
                                <li><strong>Persistent Customer Link</strong>: Dynamically injects <code>CUSTOMER STOREFRONT LINK: {url}</code> into the AI system prompt persona so greetings, catalog responses, and summaries include their personalized sign-in link (<code>[ ↗ Shop Online ]</code>).</li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li><strong>System Prompt Builder (<code>SystemPromptBuilder.php</code>)</strong>:
                    <ul>
                        <li>Embedded catalog request instructions and image tag formatting rules into the prompt builder used across all LLM models.</li>
                    </ul>
                </li>
            </ol>
            <hr>
            <h3>Updated Upload Package</h3>
            <ul>
                <li><strong>Updated Archive</strong>: <code>cpanel_upload.zip</code> (49.85 MB)</li>
                <li>Rebuilt with the updated AI orchestrator and system prompt builder.</li>
            </ul>
        </div>
    </div>

    <!-- TURN 2 -->
    <div class="turn-card">
        <div class="turn-header">Turn 2 — Architecture Critique & Semantic Resolution</div>
        <div class="question-box">
            <div class="question-title">User Question 2</div>
            <p class="question-text">so basically this code is filled with string comparisons</p>
        </div>
        <div class="response-box">
            <div class="response-title">Assistant Response 2</div>
            <p>You are <strong>100% right</strong>. Relying on procedural <code>in_array(...)</code> arrays and <code>preg_match(...)</code> regular expressions for intent classification is a brittle anti-pattern in an AI-driven platform.</p>
            <p>When a system depends on hardcoded keyword lists (e.g., checking for exact phrases like <code>"catalog"</code>, <code>"images"</code>, or <code>"photos"</code>), it breaks down the moment a user speaks naturally, uses slang, asks questions in a different language, or phrases their request unexpectedly (<em>"got pics?"</em>, <em>"what's in store?"</em>, <em>"can I see what you sell?"</em>).</p>
            <hr>
            <h3>Why the Procedural Code Accumulated</h3>
            <p>The project currently maintains <strong>two parallel execution layers</strong>:</p>
            <ol>
                <li><strong>A Legacy Procedural State Machine (<code>OrderFlowService</code>)</strong>: Built around regexes and string matching to advance rigid checkout steps.</li>
                <li><strong>An Autonomous AI Agent (<code>CommerceAgentOrchestrator</code>)</strong>: Uses LLM tool calling (OpenAI/Gemini Function Calling), RAG context, and cognitive pipelines.</li>
            </ol>
            <p>Because procedural string checks were added into <code>OrderFlowService</code> to patch intent misses, the legacy state machine began hijacking messages before the AI Agent could process them semantically—leading to fragile string comparisons.</p>
            <hr>
            <h3>The Clean Architectural Solution</h3>
            <p>To make the system truly resilient, modern, and model-agnostic:</p>
            <ol>
                <li><strong>Semantic Intent Classification (LLM-First)</strong>:
                    <ul>
                        <li>Eliminate hardcoded <code>in_array(['catalog', ...])</code> or <code>preg_match</code> gates.</li>
                        <li>Let the LLM natively resolve user intent using Function/Tool Calling (<code>get_catalog</code>, <code>process_order_message</code>, <code>search_products</code>). The model naturally understands all phrasing variations, typos, and languages without keyword dictionaries.</li>
                    </ul>
                </li>
                <li><strong>Structured Tool Returns</strong>:
                    <ul>
                        <li>Tools should return rich, structured JSON payloads instead of pre-formatted text blocks:</li>
                    </ul>
<pre><code>{
  "action": "display_catalog",
  "storefront_url": "https://...",
  "products": [
    { "id": 1, "name": "CS Book", "price": 100.0, "image_url": "https://..." }
  ]
}</code></pre>
                </li>
                <li><strong>Decoupled Media & Output Renderer</strong>:
                    <ul>
                        <li>Keep business logic free of string checks.</li>
                        <li>A single outbound delivery layer (<code>WhatsAppMessageSenderService</code>) inspects standard structured metadata on outbound messages and dispatches native WhatsApp CTA buttons and media objects cleanly.</li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>

    <div class="footer-note">
        Essem Chat AI Platform — Official Technical QA Transcript & Architecture Verification
    </div>

</body>
</html>
"""

html_path = "/home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/scratch/transcript_report.html"
pdf_path = "/home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/scratch/conversation_transcript.pdf"

os.makedirs(os.path.dirname(html_path), exist_ok=True)
with open(html_path, "w", encoding="utf-8") as f:
    f.write(html_content)

print("HTML saved to:", html_path)

cmd = [
    "chromium",
    "--headless",
    "--disable-gpu",
    "--no-sandbox",
    f"--print-to-pdf={pdf_path}",
    html_path
]

res = subprocess.run(cmd, capture_output=True, text=True)
print("Chromium exit code:", res.returncode)
if os.path.exists(pdf_path):
    print("PDF created successfully at:", pdf_path)
    print("PDF file size:", os.path.getsize(pdf_path), "bytes")
else:
    print("PDF generation failed:", res.stderr)
