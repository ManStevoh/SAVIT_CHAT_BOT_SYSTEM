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
