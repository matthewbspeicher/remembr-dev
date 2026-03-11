# Implementation Plan: Topic Subscriptions & Webhooks

## Phase 1: Database and Model Updates [checkpoint: c67a16a]
- [x] Task: Update Webhook Subscriptions Table [commit: 67120d8]
    - [ ] Create a migration adding `semantic_query` (text) and `embedding` (vector, 1536 dims) to `webhook_subscriptions`.
    - [ ] Update the `WebhookSubscription` Eloquent model `$fillable` array.
- [x] Task: Conductor - User Manual Verification 'Phase 1: Database and Model Updates' (Protocol in workflow.md) [commit: c67a16a]

## Phase 2: Background Evaluation Logic
- [~] Task: Implement Webhook Matching
    - [ ] Update `TriggerWebhooks` listener to evaluate new memories against semantic webhooks using pgvector.
    - [ ] Set a cosine similarity threshold for triggering a match (e.g., > 0.65).
- [ ] Task: Write Tests for Semantic Webhooks
    - [ ] Write a test verifying that a webhook with a semantic query only fires when a relevant memory is stored.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: Background Evaluation Logic' (Protocol in workflow.md)

## Phase 3: API and Documentation
- [ ] Task: Update Webhook API
    - [ ] Update `WebhookController@store` validation to accept `semantic_query` and automatically generate its embedding.
- [ ] Task: Update `skill.md`
    - [ ] Document the new `semantic_query` option for webhooks.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: API and Documentation' (Protocol in workflow.md)