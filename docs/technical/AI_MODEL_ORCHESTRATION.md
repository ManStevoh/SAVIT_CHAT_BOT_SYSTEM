# SAVIT AI Model Orchestration

**Do not call OpenAI, Anthropic, or Gemini directly from business logic.** Use `AiOrchestrator` or `AiGateway`.

---

## Architecture

```text
                    SAVIT Application
                           │
                   AiOrchestrator
                     chat / reason / vision / embed / transcribe
                           │
                      AiGateway
                           │
                   AiModelResolver  ← config/ai.php use_cases
                           │
         ┌─────────────────┼─────────────────┐
         │                 │                 │
    Reasoning          Fast chat          Vision / STT
    (gpt-4o)          (gpt-4o-mini)      (gpt-4o / whisper)
         │                 │                 │
              OpenAI · Anthropic · Google Gemini
```

---

## Which model for which job

| Job | Capability slot | Platform default | When to upgrade |
|-----|-----------------|------------------|-----------------|
| **Deep reasoning** (agent tools, planning, reflection) | `reasoning` | **gpt-4o** | Claude 3.5 Sonnet for long workflows |
| **Customer chat** (WhatsApp, proactive) | `chat` | **gpt-4o-mini** | Company can pick model in Settings |
| **Fast chat** (thanks, hi, ok) | `fast_chat` | **gpt-4o-mini** | Auto-selected for trivial messages |
| **Vision / OCR** (product photos, receipts) | `vision` | **gpt-4o** | Same slot handles multimodal OCR |
| **Speech-to-text** (voice notes) | `stt` | **whisper-1** | Logged + billed via gateway |
| **Text-to-speech** | `tts` | **tts-1** | Stub slot — not wired to WhatsApp yet |
| **Embeddings** (FAQ, products, knowledge) | `embedding` | **text-embedding-3-small** | pgvector/Pinecone at very large scale |
| **Marketing images** | `image` | **gemini-2.5-flash-image** | Gemini native image API |
| **Intent detection** | `fast_chat` (optional LLM) | Rules first, fast LLM fallback | Dedicated classifier model later |
| **Entity extraction** | `fast_chat` (optional LLM) | Rules + LLM for complex orders | — |

### Do **not** use LLMs for

| Problem | Handler |
|---------|---------|
| Tax calculation | Business rules |
| M-Pesa verification | API integration |
| Inventory counts | Database |
| Route optimization | Algorithm |
| Fraud scoring | ML classifier (roadmap) |
| Sales forecasting | `ForecastService` (heuristics today) |
| Product recommendations | Recommendation engine (roadmap) |

Configured in `config/ai.php` → `deterministic_handlers`.

---

## Code examples

```php
use App\Services\AI\AiOrchestrator;
use App\Services\AI\AiUseCase;

// Customer WhatsApp (auto fast-routes "thanks" → fast_chat)
$orchestrator->chat($messages, $company, AiUseCase::WHATSAPP, chatId: $chatId, latestUserMessage: $text);

// Deep reasoning JSON trace
$orchestrator->reason($messages, $company, chatId: $chatId);

// Agent tool loop (uses reasoning slot)
$orchestrator->completeWithTools($messages, $tools, $company, chatId: $chatId);

// Vision / receipt OCR
$orchestrator->vision($company, $imageUrl, $instruction, chatId: $chatId);

// Embeddings
$orchestrator->embed($text, $company);

// Voice note → text
$orchestrator->transcribe($filePath, $filename, $company);

// Intent + entities (rules first)
$orchestrator->classifyIntent($message, $company);
$orchestrator->extractEntities($message, $company);
```

Legacy code may still use `OpenAiClient` or `AiGateway` — both route through `AiModelResolver` with use-case hints.

---

## Company vs platform model selection

| Mode | Applies to |
|------|------------|
