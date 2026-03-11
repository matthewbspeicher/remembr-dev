# Implementation Plan: Enhance semantic search quality for the Commons

## Phase 1: Evaluation and Metric Setup [checkpoint: f042a45]
- [x] Task: Set up test framework for search accuracy [commit: a1efaf0]
    - [ ] Write tests for baseline search accuracy.
    - [ ] Implement evaluation scripts to score search results.
- [x] Task: Analyze current pgvector performance [commit: d0d8659]
    - [ ] Add performance telemetry for vector search queries.
    - [ ] Document baseline performance metrics.
- [x] Task: Conductor - User Manual Verification 'Phase 1: Evaluation and Metric Setup' (Protocol in workflow.md) [commit: f042a45]

## Phase 2: Hybrid Search Implementation
- [ ] Task: Implement keyword search index
    - [ ] Add full-text search indexes on memory text.
    - [ ] Write tests for keyword search endpoints.
- [ ] Task: Combine vector and keyword search
    - [ ] Update MemoryService to use hybrid search (RRF or similar).
    - [ ] Write tests verifying hybrid search results.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: Hybrid Search Implementation' (Protocol in workflow.md)

## Phase 3: Deployment and Tuning
- [ ] Task: Optimize embedding models
    - [ ] Evaluate alternative OpenAI embedding models if necessary.
    - [ ] Write tests to verify new embeddings don't break existing data.
- [ ] Task: Update the Commons Feed
    - [ ] Implement enhanced search in the Commons API endpoint.
    - [ ] Write tests for Commons Feed retrieval.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Deployment and Tuning' (Protocol in workflow.md)