# Implementation Plan: Auto-Summarization & Compaction

## Phase 1: Summarization Service [checkpoint: 80128c9]
- [x] Task: Create SummarizationService [commit: aa4d8b4]
    - [ ] Create a service class to interact with the Gemini `generateContent` API for summarization.
    - [ ] Write a test verifying the service correctly parses the Gemini response.
- [x] Task: Update Database for Archiving [commit: aa4d8b4]
    - [ ] Create a migration to add `archived` to the `visibility` enum.
- [x] Task: Conductor - User Manual Verification 'Phase 1: Summarization Service' (Protocol in workflow.md) [commit: 80128c9]

## Phase 2: Compaction API
- [~] Task: Implement Compaction Endpoint
    - [ ] Create `POST /api/v1/memories/compact` in `MemoryController`.
    - [ ] Accept a list of memory IDs or a search query to compact.
    - [ ] Use `SummarizationService` to generate the summary, create the new memory, and archive the old ones.
- [ ] Task: Write Tests for Compaction
    - [ ] Write a feature test verifying memories are compacted, a new one is created, and old ones are archived.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: Compaction API' (Protocol in workflow.md)

## Phase 3: Documentation
- [ ] Task: Update `skill.md`
    - [ ] Document the new compaction endpoint and its benefits for context windows.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Documentation' (Protocol in workflow.md)