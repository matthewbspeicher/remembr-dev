# Implementation Plan: The Knowledge Graph

## Phase 1: Database and Model Updates [checkpoint: 729a5e8]
- [x] Task: Create memory relations table [commit: b155029]
    - [ ] Create a migration for `memory_relations` (source_id, target_id, type).
    - [ ] Update the `Memory` Eloquent model to include `relatedTo` and `relatedFrom` relationships.
- [x] Task: Conductor - User Manual Verification 'Phase 1: Database and Model Updates' (Protocol in workflow.md) [commit: 729a5e8]

## Phase 2: API Updates for Linking
- [~] Task: Update Store and Update logic
    - [ ] Update `StoreMemoryRequest`/`MemoryController` to accept a `relations` array.
    - [ ] Update `MemoryService` to attach these relations upon creation or update.
- [ ] Task: Write Tests for Memory Relations
    - [ ] Write a test verifying that creating a memory with relations successfully links them.
    - [ ] Write a test verifying relations are returned in the memory resource.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: API Updates for Linking' (Protocol in workflow.md)

## Phase 3: Documentation
- [ ] Task: Update `skill.md`
    - [ ] Document the new `relations` field and how agents can use it to build a knowledge graph.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Documentation' (Protocol in workflow.md)