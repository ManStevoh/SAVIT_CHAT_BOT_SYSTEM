# 🚧 System Maintenance Mode & Developer Handover Guide

This document details the exact changes made to implement **System Maintenance Mode** for incoming WhatsApp messages and provides a comprehensive technical reference for picking up development in the future.

---

## 🛠️ 1. What Was Done (Maintenance Mode Implementation)

We added a lightweight, zero-database-migration maintenance mode toggle to intercept all incoming WhatsApp customer messages before any AI pipeline or domain logic executes.

### **Files Modified**

1. **[`LARAVEL_BACKEND/config/agent.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/config/agent.php#L24-L37)**:
   Added configuration settings:
   ```php
   'system_maintenance_enabled' => (bool) env('SYSTEM_MAINTENANCE_ENABLED', true),
   'system_maintenance_message' => env(
       'SYSTEM_MAINTENANCE_MESSAGE',
       "🚧 *System Under Maintenance*\n\nOur service is currently undergoing scheduled system updates to improve performance and add new features. We will be back online shortly!\n\nThank you for your patience."
   ),
   ```

2. **[`LARAVEL_BACKEND/app/Jobs/ProcessIncomingWhatsAppMessage.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/app/Jobs/ProcessIncomingWhatsAppMessage.php#L233-L248)**:
   Added the maintenance interceptor right at the top of the job execution method (`handleLocked`):
   ```php
   if (config('agent.system_maintenance_enabled', false)) {
       $maintenanceMsg = (string) config(
           'agent.system_maintenance_message',
           "🚧 *System Under Maintenance*\n\nOur service is currently undergoing scheduled system updates to improve performance and add new features. We will be back online shortly!\n\nThank you for your patience."
       );

       $this->sendReplyAndSave($waSender, $company, $chat, $maintenanceMsg, 'system_maintenance');

       return;
   }
   ```

---

## ⚙️ 2. How to Toggle Maintenance Mode On / Off

### **To Turn Maintenance Mode OFF (Normal Live Bot Operations):**
Edit `config/agent.php` and set `'system_maintenance_enabled' => false`:
```php
'system_maintenance_enabled' => false,
```
*(Or set `SYSTEM_MAINTENANCE_ENABLED=false` in your `.env` file if present).*

### **To Turn Maintenance Mode ON:**
Edit `config/agent.php` and set `'system_maintenance_enabled' => true`:
```php
'system_maintenance_enabled' => true,
```

---

## 🏗️ 3. Architecture & Developer Handover (How the Pipeline Works)

When maintenance mode is disabled (`system_maintenance_enabled = false`), incoming WhatsApp customer messages execute through the modern **ConversationalOS Architecture**:

```
Meta WhatsApp Webhook
       │
       ▼
ProcessIncomingWhatsAppMessage (Job / Lock Failsafe)
       │
       ▼
WhatsAppChannelAdapter (Layer 1 Channel Normalization)
       │
       ▼
ConversationalOSPipeline (Pipeline Orchestration)
       │
       ├──► 1. ConversationStateHydrator (Hydrates DTO from DB Chat)
       ├──► 2. UnifiedIntentClassifierService (Layer 2 NLU via OpenAI gpt-4o-mini)
       ├──► 3. WorkflowEngine (Layer 3 State Machine & Failsafes)
       ├──► 4. DomainServiceDispatcher (Layer 4 Domain Services: Cart/Order/Fulfillment)
       └──► 5. ResponseSpecRenderer (Layer 2b NLG State-Driven Renderer)
       │
       ▼
WhatsAppChannelAdapter::sendOutbound (Outbound WhatsApp API / Image Cards)
```

---

## 📂 Key Service Files Reference

| Area | Component | File Path |
| :--- | :--- | :--- |
| **Pipeline Core** | `ConversationalOSPipeline` | [`app/Services/Workflow/ConversationalOSPipeline.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/app/Services/Workflow/ConversationalOSPipeline.php) |
| **State Machine** | `WorkflowEngine` | [`app/Services/Workflow/WorkflowEngine.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/app/Services/Workflow/WorkflowEngine.php) |
| **NLG Renderer** | `ResponseSpecRenderer` | [`app/Services/Workflow/ResponseSpecRenderer.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/app/Services/Workflow/ResponseSpecRenderer.php) |
| **NLU Classifier** | `UnifiedIntentClassifierService` | [`app/Services/AI/UnifiedIntentClassifierService.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/app/Services/AI/UnifiedIntentClassifierService.php) |
| **Payment Engine** | `OrderPaymentService` | [`app/Services/OrderPaymentService.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/app/Services/OrderPaymentService.php) |
| **Channel Adapter**| `WhatsAppChannelAdapter` | [`app/Services/Channels/WhatsAppChannelAdapter.php`](file:///home/staticlumen/Projects/essemchat/LARAVEL_BACKEND/app/Services/Channels/WhatsAppChannelAdapter.php) |

---

## 📦 Deployment Instructions

Whenever you make changes and want to deploy to cPanel:
1. Run the zipping script from the root folder:
   ```bash
   python3 scripts/pack-cpanel.py
   ```
2. Upload the generated zip file **`EssemChat-cPanel.zip`** to your cPanel web root and extract it.
