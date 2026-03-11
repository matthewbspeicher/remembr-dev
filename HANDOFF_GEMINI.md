# Handoff: Agent Memory Commons → Gemini

> Written 2026-03-11 by Claude (Opus 4.6) after a crashed session.
> The worktree `.claude/worktrees/compassionate-murdock` was lost — no branch survived.

---

## What this project is

**Agent Memory Commons** — a shared memory API for AI agents.
Agents register, store/search memories via vector embeddings, and optionally share them on a public feed.
The public feed ("Commons Stream") is the viral/social surface.

**Stack:** Laravel 12 · PHP 8.3 · PostgreSQL + pgvector · OpenAI embeddings · Railway + Supabase (deployed)

**Live at:** https://agentmemory.dev

---

## Current state (main branch, 835bf2a)

### What's done and working
- Full REST API: agent registration, memory CRUD, semantic search, public commons feed
- pgvector-backed embeddings (`text-embedding-3-small`, 1536 dims, cached by content hash)
- Bearer token auth (`amc_` prefixed tokens) via custom middleware
- Real-time SSE Commons Stream dashboard (`public/dashboard.html`)
- Root domain redirects to Commons dashboard
- Deployed on Railway + Supabase
- PHP SDK in `sdk/src/`
- Pest test suite in `tests/Feature/MemoryApiTest.php` (mocks embeddings)

### The "Hivemind Escape Room" demo (`hivemind-escape-agent/`)
- Python agent that connects to the Commons API, reads clues, uses an LLM to reason, posts findings back
- Supports OpenAI, Anthropic, and Google Gemini as LLM providers
- Has a small uncommitted change: `.env.example` adds `OPENAI_MODEL=gpt-4.1-nano` override

### Uncommitted / untracked files
```
modified:   hivemind-escape-agent/.env.example   (adds OPENAI_MODEL env var)
untracked:  hivemind-escape-agent/__pycache__/    (ignore)
untracked:  hivemind-escape-agent/requirements.txt (new: pip deps)
```

### 10 local commits not yet pushed to origin
From oldest to newest:
1. Initial commit
2. Agent management, memory browser, production hardening
3. Marketing campaign plan + implementation
4. Deployment infra (Railway + Supabase + Resend)
5. Confluence documentation
6. DB/cache deployment fixes
7. `/commons` endpoint bypass for OpenAI embedding
8. Real-time Commons Stream dashboard
9. Terminal aesthetics & SDK snippets polish
10. Root domain → dashboard redirect

---

## What was likely in progress (lost worktree)

The crashed session was in a worktree called `compassionate-murdock`. No branch or stash survived, so the exact work is unknown. Based on the project's `CLAUDE.md` roadmap and what's already done, the most likely next tasks were:

1. **Rate limiting** — `throttle:60,1` per agent token on store/search (listed as priority #2 in CLAUDE.md)
2. **User registration UI** — Vue 3 + Inertia magic-link flow for owner tokens (priority #3)
3. **`skill.md` hosting** — serve `GET /skill.md` so MCP agents can self-onboard (priority #5)
4. **Push the 10 local commits** to origin

---

## Key files to know

| File | Purpose |
|------|---------|
| `CLAUDE.md` | Full project context, setup steps, architecture decisions, roadmap |
| `routes/api.php` | All API routes, prefixed `/v1` |
| `app/Services/MemoryService.php` | Core business logic |
| `app/Services/EmbeddingService.php` | OpenAI embedding wrapper |
| `app/Http/Middleware/AuthenticateAgent.php` | Bearer token auth |
| `app/Models/Agent.php`, `Memory.php` | Eloquent models |
| `public/dashboard.html` | Live Commons Stream UI |
| `hivemind-escape-agent/agent.py` | Python demo agent |
| `tests/Feature/MemoryApiTest.php` | Pest test suite |

---

## How to run locally

```bash
# Prerequisites: PHP 8.3, Composer, PostgreSQL with pgvector extension
cp .env.example .env   # fill in DB creds + OPENAI_API_KEY
composer install
php artisan migrate
php artisan serve       # → http://localhost:8000
php artisan test        # runs Pest suite (embeddings mocked)
```

---

## Design decisions (don't change without reason)

- **pgvector, not a separate vector DB** — simplicity, Postgres does everything
- **`text-embedding-3-small`** — cheapest OpenAI model, 1536 dims, good quality
- **Embeddings cached by content hash** — identical values embedded once
- **`skill.md` at root** — MCP agent self-onboarding discovery file
- **`amc_` token prefix** — easy to identify in logs, grep for leaks

---

## Suggested next steps

1. Push the 10 local commits: `git push origin main`
2. Pick up rate limiting or `skill.md` hosting (smallest wins on the roadmap)
3. Commit the `hivemind-escape-agent/requirements.txt` and `.env.example` change
4. Add `.gitignore` entry for `hivemind-escape-agent/__pycache__/`
