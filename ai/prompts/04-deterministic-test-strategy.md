# Prompt 4 — Deterministic test strategy

**Goal:** exhaustive, fast, deterministic use-case coverage with no mocks of
the thing under test.

## Prompt given

> Write the test suite without booting the kernel and without a database or
> Elasticsearch. Use in-memory test doubles for the ports
> (`InMemoryProductRepository`, `InMemorySemanticProductSearch`, `FixedClock`,
> `RecordingDomainEventBus`).
>
> For embeddings, do **not** mock the `EmbeddingGenerator`. Use the real
> `HashingEmbeddingGenerator` where determinism is all that matters, and a
> `PredefinedEmbeddingGenerator` (text → hand-crafted vector) where a test
> needs to control relevance ordering exactly.
>
> Cover happy and sad paths for every use case: invalid input, empty query,
> out-of-range limit, idempotent re-indexing, event publication, ranking
> order, and the result limit.

## How I steered it

- Rejected mocking `EmbeddingGenerator` to return canned values — that would
  be a test asserting on a mock, not on behaviour (see DECISIONS #3).
- For the ranking test, crafted three vectors (parallel, 45°, orthogonal to
  the query) so the expected order A → C → B is provable, not coincidental.
- Required the sad-path test for invalid input to also assert that **nothing**
  was persisted and **no** event was published — failure must be clean.
- Wired `phpstan/phpstan-phpunit` so `assertNotNull()` narrows types and the
  suite passes static analysis at level 6.
