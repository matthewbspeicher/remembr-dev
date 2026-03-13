# Structured Memory Types + MCP One-Liner Onboarding

**Date:** 2026-03-13
**Status:** Draft
**Scope:** Memory type enum, MCP npm publishing, skill.md refresh, commons seeder, SDK updates

---

## Problem

Remembr stores memories as untyped text blobs. Agents can't filter by what kind of knowledge they're looking for, and the commons has no way to surface structured results. Meanwhile, MCP onboarding requires cloning a repo and manual setup — too much friction for a launch.

## Solution

1. Add a first-class `type` enum column to memories
2. Publish the MCP server to npm for one-line setup
3. Refresh skill.md with complete API documentation
4. Seed the commons with curated developer knowledge
5. Update both SDKs with type support

---

## 1. Memory Type System

### 1.1 Canonical Types

8 fixed types. Agents must pick from this list.

| Type | Purpose | Example Value |
|---|---|---|
| `fact` | Objective knowledge | "PostgreSQL IVFFlat indexes require > 100 rows to build" |
| `preference` | User or agent preferences | "This user prefers dark mode and terse responses" |
| `procedure` | Step-by-step instructions | "To deploy: git push origin main, then railway up" |
| `lesson` | Hard-won experiential knowledge | "Mocking the DB in integration tests missed a migration bug" |
| `error_fix` | Problem and its solution | "pgvector boolean mismatch: use whereRaw('col IS TRUE')" |
| `tool_tip` | API/tool usage patterns | "OpenAI embedding-3-small: 1536 dims, $0.02 per 1M tokens" |
| `context` | Session or project context | "Working on pre-launch sprint for remembr.dev" |
| `note` | General / uncategorized (default) | Anything that doesn't fit above |

### 1.2 Schema Change

**Migration: `add_type_to_memories_table`**

```php
Schema::table('memories', function (Blueprint $table) {
    $table->string('type', 20)->default('note')->after('value');
    $table->index('type');
});
```

- Column: `VARCHAR(20) NOT NULL DEFAULT 'note'`
- Existing memories automatically get `'note'`
- Index for fast filtering

### 1.3 Validation

Add to `MemoryController` store/update validation:

```php
'type' => ['sometimes', 'string', Rule::in([
    'fact', 'preference', 'procedure', 'lesson',
    'error_fix', 'tool_tip', 'context', 'note',
])],
```

Define the enum list as a constant on the `Memory` model:

```php
const TYPES = [
    'fact', 'preference', 'procedure', 'lesson',
    'error_fix', 'tool_tip', 'context', 'note',
];
```

### 1.4 Search Integration

**API filter:** Add optional `type` query parameter to search endpoints:
- `GET /v1/memories/search?q=postgres&type=error_fix`
- `GET /v1/commons/search?q=postgres&type=error_fix`
- `GET /v1/memories?type=fact` (list endpoint)

**Implementation in MemoryService:**
- Add `->when($type, fn($q) => $q->where('type', $type))` to search and list queries
- No RRF boost by type for now (keep it simple, add later if needed)

### 1.5 Model Changes

- Add `'type'` to `$fillable`
- Add `TYPES` constant
- Add `scopeOfType($query, string $type)` scope

---

## 2. MCP One-Liner Onboarding

### 2.1 The User Experience

```
1. Sign up at remembr.dev
2. Create agent in dashboard → copy token
3. Add to claude_desktop_config.json:
```

```json
{
  "mcpServers": {
    "remembr": {
      "command": "npx",
      "args": ["-y", "@remembr/mcp-server"],
      "env": {
        "REMEMBR_AGENT_TOKEN": "amc_your_token_here"
      }
    }
  }
}
```

### 2.2 npm Package: `@remembr/mcp-server`

**Changes to `mcp-server/package.json`:**

```json
{
  "name": "@remembr/mcp-server",
  "version": "0.1.0",
  "description": "MCP server for Remembr — persistent memory for AI agents",
  "bin": {
    "remembr-mcp": "./index.js"
  },
  "files": ["index.js", "package.json", "README.md"],
  "keywords": ["mcp", "ai", "memory", "agent", "remembr"],
  "license": "MIT"
}
```

**Changes to `mcp-server/index.js`:**
- Add `#!/usr/bin/env node` shebang as first line
- Ensure `REMEMBR_BASE_URL` defaults to `https://remembr.dev`

**New `mcp-server/README.md`:**
- Quick setup instructions
- Environment variables reference
- Link to full docs

### 2.3 MCP Tool Updates

Update `store_memory` tool schema to include `type`:

```json
{
  "name": "store_memory",
  "description": "Store a memory. Choose the type that best fits...",
  "inputSchema": {
    "properties": {
      "type": {
        "type": "string",
        "enum": ["fact", "preference", "procedure", "lesson", "error_fix", "tool_tip", "context", "note"],
        "description": "Memory type. fact=objective knowledge, preference=user/agent prefs, procedure=how-to steps, lesson=experiential learning, error_fix=problem+solution, tool_tip=API/tool patterns, context=session state, note=general",
        "default": "note"
      }
    }
  }
}
```

Update `search_memories` and `search_commons` tools to accept optional `type` filter.

### 2.4 Dashboard Copy-Paste UX

When a user creates or views an agent in the dashboard, show a "Quick Setup" card:

- Pre-filled JSON config block with the agent's actual token inserted
- Copy-to-clipboard button
- Works for both Claude Desktop and Claude Code (`claude mcp add`) formats

**Files affected:** `resources/js/Pages/Dashboard.vue`

---

## 3. Refreshed skill.md

Complete rewrite of `public/skill.md` to cover the full API surface:

### Sections

1. **What is Remembr** — one-paragraph explanation
2. **Quick Setup** — MCP one-liner config block
3. **Memory Types** — table of all 8 types with when-to-use guidance
4. **API Reference** — all endpoints with examples:
   - Store (with type, tags, importance, confidence, ttl)
   - Get / List / Update / Delete
   - Search (own memories + commons, with type filter)
   - Share
   - Workspaces
   - Memory relations
5. **Best Practices** — when to use each type, importance/confidence guidelines, tagging conventions
6. **Memory Object Shape** — complete JSON example with all fields

---

## 4. Curated Starter Commons

### 4.1 Seeder Command

`php artisan db:seed --class=CommonsSeeder`

- Creates a system agent: `name: "Remembr"`, `description: "Curated developer knowledge"`
- Agent flagged with `metadata->is_system: true` for UI badge treatment
- Idempotent: skips if system agent already exists

### 4.2 Seed Content

~40 memories across types:

**error_fix (~10):**
- PostgreSQL boolean comparison (use `IS TRUE` not `= 1`)
- CORS preflight missing `Access-Control-Allow-Headers`
- Node.js ESM `__dirname` not defined (use `import.meta.url`)
- Docker DNS resolution in compose (use service names)
- Git detached HEAD recovery
- Laravel N+1 query detection
- Redis serialization mismatch (phpredis vs predis)
- SSL certificate chain incomplete
- Timezone discrepancy between PHP and database
- npm peer dependency conflicts resolution

**tool_tip (~10):**
- OpenAI API: rate limits and retry-after headers
- GitHub API: pagination with Link header
- curl: `-s -S` flags for silent but show errors
- Redis: TTL best practices (don't set TTL on every key)
- PostgreSQL: EXPLAIN ANALYZE for query optimization
- Docker: multi-stage builds to reduce image size
- Laravel: `artisan tinker` with `--execute` for scripting
- pgvector: IVFFlat vs HNSW index tradeoffs
- SSH: agent forwarding with `-A` flag
- jq: common filters for API response parsing

**procedure (~8):**
- SSH key generation and GitHub setup
- PostgreSQL extension installation (pgvector)
- Laravel queue worker configuration for production
- Docker compose for local dev with hot reload
- Git bisect workflow for finding regressions
- Laravel migration rollback and recovery
- Let's Encrypt certificate setup with certbot
- Database backup and restore with pg_dump

**fact (~7):**
- HTTP status codes: 418 is "I'm a Teapot" (RFC 2324)
- UUID v4 vs ULID: ULID is sortable by time
- JWT has three parts: header.payload.signature
- Embedding dimensions: higher isn't always better
- PostgreSQL max connections default is 100
- Base64 encoding increases size by ~33%
- UTF-8 uses 1-4 bytes per character

**lesson (~5):**
- Don't mock the database in integration tests
- Cache invalidation: prefer short TTLs over manual invalidation
- Feature flags add complexity — remove them once rolled out
- Log structured JSON, not human-readable strings
- Write the test first when fixing a bug — proves the fix works

### 4.3 Embedding Backfill Command

`php artisan memories:embed-missing`

- Finds all memories where `embedding IS NULL`
- Processes in batches of 50
- Uses `EmbeddingService::embedBatch()`
- Logs progress
- Useful beyond seeding — handles any failed embedding calls

---

## 5. SDK Updates

### 5.1 JS/TS SDK (`sdk/js`)

- Add `type` to `StoreOptions` interface in `types.ts`
- Add `type` to `Memory` interface
- Add optional `type` parameter to `search()` and `searchCommons()` in `SearchOptions`
- Add `type` to `remember()` method parameter forwarding

### 5.2 PHP SDK (`sdk/src`)

- Add `type` parameter to `AgentMemoryClient::remember()`
- Add `type` filter to `search()` and `searchCommons()`
- Update docblocks with type enum values

---

## 6. Landing Page Updates

Update the curl example on `Home.vue` to showcase the type field:

```bash
curl -X POST https://remembr.dev/api/v1/memories \
  -H "Authorization: Bearer amc_..." \
  -d '{"value":"IVFFlat needs >100 rows to build","type":"error_fix","tags":["postgresql","pgvector"]}'
```

---

## Files to Create/Modify

### New Files
- `database/migrations/XXXX_add_type_to_memories_table.php`
- `database/seeders/CommonsSeeder.php`
- `app/Console/Commands/EmbedMissingMemories.php`
- `mcp-server/README.md` (rewrite)

### Modified Files
- `app/Models/Memory.php` — add TYPES const, type to fillable, scopeOfType
- `app/Http/Controllers/Api/MemoryController.php` — type validation + filter
- `app/Services/MemoryService.php` — type filter in search/list
- `mcp-server/index.js` — add shebang, update tool schemas with type
- `mcp-server/package.json` — add bin, npm metadata, @remembr scope
- `public/skill.md` — complete rewrite
- `sdk/js/src/types.ts` — add type to interfaces
- `sdk/js/src/index.ts` — forward type parameter
- `sdk/src/AgentMemoryClient.php` — add type parameter
- `resources/js/Pages/Home.vue` — update curl example
- `resources/js/Pages/Dashboard.vue` — add MCP config copy-paste card

### Coordination with Gemini
Gemini is building: live demo bots (scheduled), interactive playground, quickstart video.
The `type` migration should land before Gemini's playground work so playground memories can use types.

---

## Testing

- Migration test: verify column exists and defaults to 'note'
- Validation test: reject invalid types, accept valid types
- Search filter test: `?type=error_fix` returns only error_fix memories
- Seeder test: run seeder, verify memories created with correct types
- Embed-missing command test: verify it processes memories without embeddings
- MCP tool test: verify type parameter accepted and forwarded
