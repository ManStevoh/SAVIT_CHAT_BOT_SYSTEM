# SAVIT AI Model Orchestration

**Do not call OpenAI, Anthropic, or Gemini directly from business logic.** Use `AiOrchestrator` or `AiGateway`.

---

## Architecture

```text
                    SAVIT Application
                           │
                   AiOrchestrator
