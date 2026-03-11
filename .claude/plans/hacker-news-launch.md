# HackerNews Launch Prep — Implementation Plan

## Goal
Make remembr.dev compelling enough that a Show HN post converts visitors into users.

---

## Phase 1: Landing Page (Claude)

**File**: `public/index.html` (replace Laravel welcome or serve from route)

- Hero section: "Your AI agent's long-term memory" + one-liner
- 3-feature grid: Store, Search, Share
- Live curl example showing the API in action
- "Get Started Free" CTA → `/auth/login`
- Live commons counter pulled from `/api/v1/commons/stream` SSE
- Dark theme, clean, fast — no build step, vanilla HTML/CSS/JS
- Mobile responsive

## Phase 2: Rate Limit Headers (Claude)

**File**: `app/Http/Middleware/RateLimitHeaders.php` + register in bootstrap

- Add standard headers to all API responses:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset` (Unix timestamp)
- Laravel's built-in throttle already tracks this; just surface it in headers

## Phase 3: MCP Server (Claude)

**Directory**: `mcp-server/`

- Node.js MCP server (stdio transport)
- Tools: `store_memory`, `search_memories`, `get_memory`, `search_commons`
- Config: agent token via env var
- README with install instructions for Claude Code settings
- Publishable to npm as `@remembr/mcp-server`

## Phase 4: README.md (Claude)

**File**: `README.md`

- What it is (2 sentences)
- Why it exists (the problem)
- Quickstart: register agent → store → search (curl)
- Architecture section (Postgres + pgvector, embeddings)
- SDK links (PHP, Python, JS)
- MCP server setup
- Self-hosting instructions
- License

## Phase 5: Seed Script (Claude)

**File**: `app/Console/Commands/SeedCommonsCommand.php`

- Creates 2-3 demo agents with distinct personalities
- Each stores 5-10 public memories covering different topics
- Makes the commons feed look alive on first visit
- Idempotent (won't duplicate on re-run)

## Phase 6: OpenAPI Spec (Claude)

**File**: `public/openapi.json` + route at `/docs`

- Full OpenAPI 3.0 spec for all v1 endpoints
- Includes auth scheme, request/response schemas
- Serve with Swagger UI or Scalar at `/docs`

## Phase 7: Gemini Handoff — SDKs + Discord Bot

**Handoff doc**: `docs/gemini-handoff.md`

### Python SDK
- pip package `remembr`
- Classes: `RemembrClient` with methods matching API
- Async support via httpx
- Published to PyPI

### TypeScript/JS SDK
- npm package `@remembr/sdk`
- Typed client with all API methods
- Works in Node.js and edge runtimes
- Published to npm

### Discord Bot
- Connects to `/api/v1/commons/stream` SSE
- Posts new public memories to a #commons channel
- Shows agent name, memory value, metadata
- Invite link for the RemembrDev Discord

---

## Launch Checklist
- [x] Landing page live at remembr.dev
- [x] Rate limit headers on all API responses
- [x] MCP server working in Claude Code
- [x] README on GitHub repo
- [x] Commons seeded with demo data
- [x] OpenAPI spec at /docs
- [x] Python SDK (Gemini)
- [x] JS SDK (Gemini)
- [x] Discord bot (Gemini)
- [x] Show HN post drafted
