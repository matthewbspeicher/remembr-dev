# Codebase Hardening v2 — Security, Integrity, Simplification & Module Boundaries

**Date:** 2026-04-02
**Status:** Approved
**Scope:** 19 critical fixes, 16 important fixes, 11 simplifications, 1 structural reorganization

---

## Context

A review of 35 commits (138 files, ~9,500 lines) added in the last 24 hours surfaced significant issues across security, authorization, data integrity, and code organization. Eight parallel review agents analyzed the codebase and produced findings that this spec addresses comprehensively.

The codebase has grown to 30 API controllers, 23 trading routes (38% of API surface), and 4 major feature verticals (memories, collaboration, arena, trading). The rapid growth introduced duplicated code patterns, auth gaps, dead/broken functionality, and inconsistent conventions.

---

## Phase 1 — Security Criticals

All items in this phase are deployment-blocking. They must ship before any other work.

### S1: Token Storage Hardening

**Problem:** Three places query plaintext tokens with `orWhere('api_token', $token)` fallbacks that defeat the partial hashing implementation. Database compromise exposes all tokens.

**Files:**
- `app/Providers/AppServiceProvider.php` — agent guard queries `api_token` directly
- `app/Http/Middleware/AuthenticateAgent.php:34` — workspace lookup has `orWhere('api_token', $token)`
- `app/Http/Controllers/Api/AgentController.php:27` — owner lookup has `orWhere('api_token', ...)`

**Changes:**

1. `AppServiceProvider.php` agent guard: change from `Agent::where('api_token', $token)` to `Agent::where('token_hash', hash('sha256', $token))->where('is_active', true)`.

2. `AuthenticateAgent.php` workspace guard: remove `->orWhere('api_token', $token)`. Query only `Workspace::where('api_token_hash', $tokenHash)`.

3. `AgentController.php` owner lookup: remove `->orWhere('api_token', $validated['owner_token'])`. Query only by `api_token_hash`.

4. New migration `drop_plaintext_token_columns`:
   - Backfill any `agents` rows where `token_hash` is null from `api_token`
   - Backfill any `users` rows where `api_token_hash` is null from `api_token`
   - Backfill any `workspaces` rows where `api_token_hash` is null from `api_token`
   - Assert zero null hash rows remain
   - Drop `api_token` column from `agents`, `users`, and `workspaces` tables

**Risk:** Destructive migration. Tokens never hashed become unrecoverable after column drop. The backfill step mitigates this — verify zero null `token_hash` rows before dropping.

### S2: Auth Bypass for Non-JSON Requests

**Problem:** `AuthenticateAgent.php:27-28` — when no bearer token and request doesn't `expectsJson()`, calls `$next($request)`, passing unauthenticated requests to controllers.

**Fix:** Always return 401 when no token is provided, regardless of `Accept` header. Remove the `expectsJson()` branching. Also remove duplicate `$request->bearerToken()` call at line 16.

**File:** `app/Http/Middleware/AuthenticateAgent.php`

### S3: Scope Enforcement Null Pass-Through

**Problem:** `EnforceAgentScopes.php:18` — `if ($agent && !$agent->hasScope(...))` silently passes when `$agent` is null.

**Fix:** Return 401 when `$agent` is null. The middleware is only applied to routes requiring a scope, so missing agent is always an error.

**File:** `app/Http/Middleware/EnforceAgentScopes.php`

### S4: Exception Message Leaks

**Problem:** Three catch blocks in `AuthenticateAgent.php` return `$e->getMessage()` to clients, exposing file paths, SQL, and class names.

**Fix:** Return generic `"Authentication failed."` in all three catch blocks. The existing `Log::warning` calls already capture details server-side.

**File:** `app/Http/Middleware/AuthenticateAgent.php`

### S5: Mass-Assignable Security Fields

**Problem:** `Agent.$fillable` includes `api_token`, `token_hash`, `is_active`, `max_memories`, `scopes`. `Workspace.$fillable` includes `api_token`, `api_token_hash`.

**Fix:** Remove security-sensitive fields from `$fillable`. Set them explicitly in registration, token rotation, and admin code paths. Grep all `Agent::create`, `Agent::update`, `$agent->update(`, `Workspace::create`, `Workspace::update` calls to verify none pass these fields from request data.

**Files:** `app/Models/Agent.php`, `app/Models/Workspace.php`

### S6: CSRF Bypass with Fake Token Prefix

**Problem:** `ValidateAgentCsrf.php:16` skips CSRF for any request with a `Bearer` token starting with `amc_` or `wks_`, without validating the token is real.

**Fix:** Remove the token-prefix check entirely. API routes are already CSRF-exempt in Laravel. Web routes should always validate CSRF. If agent API calls are hitting web routes, move those routes to the API middleware group.

**File:** `app/Http/Middleware/ValidateAgentCsrf.php`

### S7: `hasScope()` HTTP Coupling

**Problem:** `Agent::hasScope()` reads `request()->attributes->has('agent')` to grant humans full access. Couples the model to the HTTP cycle.

**Fix:** Remove the `request()` call from the model. The method becomes pure: `return in_array($scope, $this->scopes ?? [])`. The `EnforceAgentScopes` middleware already handles the "is this an agent?" question — it only fires on agent-authenticated routes.

**File:** `app/Models/Agent.php`

---

## Phase 2 — Broken Functionality

Items that crash or silently do nothing in production.

### T1: Register `EvaluateTradeAlerts` Listener

**Problem:** `EvaluateTradeAlerts` has handler methods but is never registered. All trade alerts are silently dead.

**Fix:** Add to `AppServiceProvider::boot()`:
```php
Event::listen(TradeClosed::class, [EvaluateTradeAlerts::class, 'handleTradeClosed']);
Event::listen(TradeOpened::class, [EvaluateTradeAlerts::class, 'handleTradeOpened']);
```

Also fix `pnl == 0` edge case (I15): change `if ($pnl > 0) ... else` to `if ($pnl > 0) ... elseif ($pnl < 0)` so break-even trades don't trigger `pnl_below`.

**Files:** `app/Providers/AppServiceProvider.php`, `app/Listeners/EvaluateTradeAlerts.php`

### T2: Fix Wrong Webhook Listener

**Problem:** `ProcessSemanticWebhooks` (weaker duplicate) is registered for `MemoryCreated` instead of `EvaluateSemanticWebhooks`. Also leaks private memory summaries in payloads.

**Fix:**
1. Change `AppServiceProvider` to register `EvaluateSemanticWebhooks` instead of `ProcessSemanticWebhooks`
2. Delete `app/Listeners/ProcessSemanticWebhooks.php`
3. Verify `EvaluateSemanticWebhooks` handles null embeddings gracefully

**Files:** `app/Providers/AppServiceProvider.php`, `app/Listeners/ProcessSemanticWebhooks.php` (delete), `app/Listeners/EvaluateSemanticWebhooks.php` (verify)

### T3: Fix `auto-compact` Command

**Problem:** `AutoCompactMemories.php:62` calls `MemoryService::compact()` which doesn't exist. Crashes every invocation.

**Fix:** Extract compaction logic from `MemoryController::compact()` into `MemoryService::compact(Agent $agent, array $memoryIds, string $summaryKey): Memory`. The command and controller both call the service method.

**Files:** `app/Services/MemoryService.php`, `app/Console/Commands/AutoCompactMemories.php`, `app/Http/Controllers/Api/MemoryController.php`

### T4: Trading Score Migration

**Problem:** `TradingService::recalculateStats()` stores `trading_score` in `ArenaProfile.personality_tags` JSON column as an acknowledged hack.

**Fix:**
1. New migration: `add_trading_score_to_arena_profiles_table` — adds nullable `trading_score` decimal column
2. Data migration: reads `personality_tags['trading_score']`, writes to new column, removes key from JSON
3. Update `TradingService::recalculateStats()` to write `$profile->trading_score` directly
4. Update any reads of `personality_tags['trading_score']`

**Files:** New migration, `app/Services/TradingService.php`, any ArenaProfile consumers

### I14: Remove Dead `EnforcePlanLimits` Middleware

**Problem:** No-op middleware — `handle()` immediately returns `$next($request)`. Applied to every authenticated route, giving false confidence.

**Fix:** Remove from route stack in `routes/api.php` and alias from `bootstrap/app.php`. Add `// TODO: Implement plan limits middleware` comment. Reimplementation is a future feature.

**Files:** `routes/api.php`, `bootstrap/app.php`, `app/Http/Middleware/EnforcePlanLimits.php` (keep file for future implementation)

---

## Phase 3 — Authorization & Data Integrity

### Collaboration Auth Gaps

**C1 + C2: Task update/assign/status authorization**

Add creator/assignee guard in `TaskController::update`, `assign`, and `updateStatus`:
```php
if ($task->created_by_agent_id !== $agent->id && $task->assigned_agent_id !== $agent->id) {
    return response()->json(['error' => 'Only the task creator or assignee can modify this task.'], 403);
}
```

For `updateStatus`: restrict `completed`/`failed` to assignee only; `cancelled` can be set by creator.

**File:** `app/Http/Controllers/Api/TaskController.php`

**C3: `MentionController::respond` missing workspace membership check**

Add `agentBelongsToWorkspace()` call before the `target_agent_id` check.

**File:** `app/Http/Controllers/Api/MentionController.php`

**I3: Events endpoint ignores subscriptions**

Filter events by the agent's active subscription `event_types`:
```php
$subscribedTypes = $agent->workspaceSubscriptions()
    ->where('workspace_id', $workspace->id)
    ->where('is_active', true)
    ->pluck('event_types')
    ->flatten()
    ->unique()
    ->toArray();
$query->whereIn('event_type', $subscribedTypes);
```

Return empty result if no active subscriptions.

**File:** `app/Http/Controllers/Api/SubscriptionController.php`

**I4: Mentions can be responded to multiple times**

Add `isPending()` guard in `CollaborationMention::respond()`. Controller catches `\LogicException` and returns 422.

**Files:** `app/Models/CollaborationMention.php`, `app/Http/Controllers/Api/MentionController.php`

**I5: `WorkspaceTest` tests nonexistent `slug` column**

Remove `slug` references from test. Test only fields in `$fillable` and migration.

**File:** `tests/Unit/Models/WorkspaceTest.php`

### Arena Data Integrity

**A1: ELO float truncation**

Wrap both updates: `(int) round($elo1 + $k * ($actual1 - $expected1))`.

**File:** `app/Services/BattleArenaService.php`

**A2: Tournament join race condition**

Wrap `joinTournament` in `DB::transaction` + `lockForUpdate`. Add participant capacity validation inside lock.

**File:** `app/Services/BattleArenaService.php`

**A3: Tournament state corruption**

Validate participant count before setting status to `in_progress`. Add max-rounds safeguard (10 rounds) — after max, highest cumulative score wins.

**File:** `app/Services/BattleArenaService.php`

**A4: Tournament uses unofficial challenges**

Extract `ArenaChallenge::scopeOfficial()`:
```php
public function scopeOfficial($query) {
    return $query->whereHas('gym', fn($q) => $q->where('is_official', true));
}
```

Both `requestMatch` and `processTournamentRound` use `ArenaChallenge::official()->inRandomOrder()->first()`.

**Files:** `app/Models/ArenaChallenge.php`, `app/Services/BattleArenaService.php`, `app/Http/Controllers/Api/ArenaMatchController.php`

**A5: Zero-vector embedding**

Replace inline zero-vector `Memory::create()` in `awardTournamentRewards` with a dispatched job so `EmbeddingService` generates the real vector asynchronously. Create memory without embedding, let job backfill.

**File:** `app/Services/BattleArenaService.php`

**I10: `ReflectionMethod` hack**

Extract `callGemini` as a public method on `SummarizationService` (or a new `GeminiClient` service). Inject normally into `BattleArenaService`.

**Files:** `app/Services/BattleArenaService.php`, `app/Services/SummarizationService.php` (or new `GeminiClient`)

**I11: Unbounded `ArenaGym::all()`**

Change to `ArenaGym::where('is_official', true)->get()`. Fix `recentMatches` stub to query actual recent matches.

**File:** `app/Http/Controllers/ArenaController.php`

**I12: `show` exposes unofficial gyms**

Add `where('is_official', true)` to `show` query.

**File:** `app/Http/Controllers/Api/ArenaGymController.php`

**I13: Double `fresh()` calls**

Call once, store in `$refreshed`, use for both fields.

**File:** `app/Http/Controllers/Api/ArenaChallengeController.php`

### Trading Logic Bugs

**I6: N+1 on leaderboard**

Add `->with('agent')` before `->get()`.

**File:** `app/Http/Controllers/Api/TradingLeaderboardController.php`

**I7: Stale model in `SummarizeMemory`**

Add `$this->memory->refresh()` before the summary guard.

**File:** `app/Jobs/SummarizeMemory.php`

**I8: Raw `$request->input('paper')` bypasses validation**

Change to `$validated['paper'] ?? true`.

**File:** `app/Http/Controllers/Api/TradingController.php`

**I9: `TradeCorrelationTest` missing `RefreshDatabase`**

Add `uses(RefreshDatabase::class)` and mock `EmbeddingService`.

**File:** `tests/Feature/TradeCorrelationTest.php`

**I16: `Auth::guard('web')->setUser()` side-effect**

Remove from `AuthenticateAgent`. API bearer token auth should not log in the owner on the web guard.

**File:** `app/Http/Middleware/AuthenticateAgent.php`

---

## Phase 4 — Deduplication & Simplification

### 4A: `ResolvesAgent` Trait

Create `app/Concerns/ResolvesAgent.php` with:
- `resolveAgent(Request $request): Agent|JsonResponse`
- `agentBelongsToWorkspace(Agent $agent, Workspace $workspace): bool`
- `resolveWorkspaceAgent(Request $request, Workspace $workspace): Agent|JsonResponse` — combines workspace membership + agent resolution

Delete local copies from: `MemoryController`, `SessionController`, `PresenceController`, `MentionController`, `TaskController`, `SubscriptionController`.

Standardize error message to `'No valid authentication context found.'` across all controllers.

### 4B: Route Model Binding for Workspaces

Change workspace routes from `{id}` to `{workspace}` in `routes/api.php`. Type-hint `Workspace $workspace` in controller method signatures. Remove all `Workspace::find($id)` + 404 guard blocks (~20 occurrences across 4 controllers).

### 4C: `$request->boolean()` Replacement

Replace all 9 instances of `filter_var($request->input('paper', ...), FILTER_VALIDATE_BOOLEAN)` with `$request->boolean('paper', true)` in: `TradingStatsController` (3x), `TradingPositionController` (2x), `PortfolioController`, `TradeExportController`, `RiskController`, `ReplayController`.

### 4D: Response Envelope Standardization

Add helper to base `Controller.php`:
```php
protected function ok($data, int $status = 200): JsonResponse
{
    return response()->json(['data' => $data], $status);
}
```

Apply across all controllers during Phase 5 restructuring. Paginated endpoints use Laravel's built-in paginator `toArray()` (already wraps `data` + `meta`).

### 4E: Business Logic Extraction

| From | To | Logic |
|------|----|-------|
| `TradingStatsController` | `TradingQueryService` | Raw SQL aggregations, Pearson correlation |
| `LeaderboardApiController::active` | `LeaderboardService::calculateStreak()` | 30-day streak calculation |
| `PortfolioController::index` | `PortfolioService::aggregate()` | Weighted average, cross-agent rollup |
| `WebhookController::store` | `WebhookService::create()` | Embedding generation side-effect |

Extract as-is, no behavior changes. Controllers become thin validate → service → respond wrappers.

### 4F: Controller Consolidation

| Merge | Into |
|-------|------|
| `AchievementController` (16 lines, 1 method) | `AgentController` |
| `ArenaGymController` + `ArenaProfileController` | Single `Api\ArenaController` |
| `StatsController` + `CommonsPollController` | New `CommonsController` |

### 4G: Python SDK Deduplication

Extract `_BaseClient` with all method bodies. `RemembrClient` and `AsyncRemembrClient` each implement only `_request()`. Eliminates ~350 lines.

### 4H: SDK HTTP Method Fixes

- Python `respond_to_mention`: `PUT` → `POST`
- Python `assign_task`: `PUT` → `POST`

### 4I: `HasSecureToken` Trait

Create `app/Concerns/HasSecureToken.php`:
```php
trait HasSecureToken {
    abstract protected function tokenPrefix(): string;
    abstract protected function tokenLength(): int;

    public static function generateToken(): string { ... }
    public static function hashToken(string $token): string { ... }
    public function ensureApiToken(): void { ... }
}
```

Apply to `Agent` (prefix `amc_`, length 60) and `Workspace` (prefix `wks_`, length 40). Replace all inline `hash('sha256', ...)` calls.

---

## Phase 5 — Trading Module Boundary

### Directory Structure

```
app/Trading/
  Controllers/
    TradingController.php
    TradingStatsController.php
    TradingLeaderboardController.php
    TradingPositionController.php
    TradeAlertController.php
    TradeExportController.php
    PortfolioController.php
    RiskController.php
    ReplayController.php
    SignalController.php
  Models/
    Trade.php
    Position.php
    TradingStats.php
    TradeAlert.php
  Services/
    TradingService.php
    TradingQueryService.php
    PortfolioService.php
    RiskService.php
    ReplayService.php
  Observers/
    TradeObserver.php
  Listeners/
    EvaluateTradeAlerts.php
    TriggerTradeWebhooks.php
  Events/
    TradeOpened.php
    TradeClosed.php
    PositionChanged.php
  Jobs/
    RecalculateTradingStats.php
```

### Namespace

All classes move to `App\Trading\*` sub-namespaces.

### Route Separation

New `routes/trading.php` with all 23 trading routes. Loaded in `bootstrap/app.php` with `/api/v1/trading` prefix and shared middleware. `routes/api.php` shrinks by ~40 lines.

### Webhook Listener Split

Split `TriggerWebhooks` into:
- `app/Listeners/TriggerMemoryWebhooks.php` — handles `MemoryShared` (stays in core)
- `app/Trading/Listeners/TriggerTradeWebhooks.php` — handles trade/position events

### Arena Coupling Decoupled via Event

Replace direct `ArenaProfile` write in `TradingService::recalculateStats()` with:
```php
TradingScoreUpdated::dispatch($agent, $tradingScore);
```

New `app/Listeners/UpdateArenaScore.php` (core) listens to this event and writes to `ArenaProfile.trading_score`.

### Test Reorganization

Move trading tests to `tests/Feature/Trading/`:
- `TradeAlertTest.php`, `TradeCorrelationTest.php`, `TradeExportTest.php`
- `TradeReplayTest.php`, `TradeTagsTest.php`, `TradeWebhookTest.php`
- `MultiAgentPortfolioTest.php`, `RiskMetricsTest.php`

### What Stays in Core

- `Agent.php` keeps trading relationship methods (standard Eloquent)
- SDK files stay in respective `sdk-*` directories
- Migrations stay in `database/migrations/` (Laravel convention)

---

## Testing Strategy

Each phase has its own verification:

- **Phase 1:** Run full test suite. Manually test token auth with `amc_` and `wks_` tokens. Verify 401 on requests without `Accept: application/json`. Verify scope enforcement returns 401 not pass-through.
- **Phase 2:** Verify `memories:auto-compact` runs without crashing. Verify trade alerts trigger on `TradeClosed` events. Verify correct webhook listener fires on `MemoryCreated`.
- **Phase 3:** Add tests for task authorization (agent B cannot update agent A's task). Test ELO rounding. Test tournament with concurrent joins. Test mention re-respond returns 422.
- **Phase 4:** Run full test suite after each dedup step. SDK tests for corrected HTTP methods.
- **Phase 5:** Run full test suite after moves. Verify all trading routes respond correctly at same URLs. No behavior changes — purely structural.

---

## Summary

| Phase | Items | Files Touched | Risk |
|-------|-------|---------------|------|
| 1 — Security | 7 fixes + 1 migration | ~15 | High (auth changes) |
| 2 — Broken Functionality | 5 fixes + 1 migration | ~8 | Medium (listener wiring) |
| 3 — Auth & Data Integrity | 16 fixes | ~20 | Medium (authorization logic) |
| 4 — Deduplication | 9 simplifications | ~35 | Low (no behavior changes) |
| 5 — Module Boundary | Structural move + 2 splits | ~40 (mostly renames) | Low (no behavior changes) |
