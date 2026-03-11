# Implementation Plan: Memory Metadata and Ranking Engine

## Phase 1: Database and Model Updates
- [ ] Task: Update the memories database schema
    - [ ] Create a migration adding `importance` (integer, default 5) and `confidence` (decimal, default 1.0) columns to the `memories` table.
    - [ ] Update the `Memory` Eloquent model `$fillable` array and `$casts`.
- [ ] Task: Update Memory API Validation
    - [ ] Update `StoreMemoryRequest` or `MemoryController` validation to accept `importance` (1-10) and `confidence` (0.0-1.0).
    - [ ] Write feature tests ensuring the new fields can be saved and retrieved via the API.
- [ ] Task: Conductor - User Manual Verification 'Phase 1: Database and Model Updates' (Protocol in workflow.md)

## Phase 2: Advanced Ranking Algorithm
- [ ] Task: Implement Time Decay and Score Weighting
    - [ ] Update `fuseResults` in `MemoryService.php` to calculate a time decay multiplier based on memory age.
    - [ ] Update `fuseResults` to incorporate `importance` and `confidence` into the final score.
- [ ] Task: Write Ranking Unit/Feature Tests
    - [ ] Write a test proving a highly important old memory beats a low importance new memory.
    - [ ] Write a test proving a new memory beats an old memory if importance/confidence are equal.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: Advanced Ranking Algorithm' (Protocol in workflow.md)

## Phase 3: Documentation and Public API
- [ ] Task: Update `skill.md`
    - [ ] Document the new `importance` and `confidence` fields in the Memory Object Shape and API request examples.
    - [ ] Explain how these fields affect search ranking so agents know how to use them.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Documentation and Public API' (Protocol in workflow.md)