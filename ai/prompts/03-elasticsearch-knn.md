# Prompt 3 — Elasticsearch read model and kNN search

**Goal:** a real vector search adapter, with no invented APIs.

## Prompt given

> Implement the read side in Elasticsearch. Create the index with a
> `dense_vector` field (`similarity: cosine`). Implement an adapter that
> satisfies both `SemanticProductSearch` (query) and `ProductSearchIndex`
> (projection). Search uses approximate `knn`.
>
> Use the **installed** `elasticsearch/elasticsearch` 8.x client — check the
> real method signatures, do not assume them. The Elasticsearch kNN `_score`
> for cosine is `(1 + cosine) / 2`; convert it back to a raw cosine before
> building a `RelevanceScore`.

## How I steered it

- Caught the temptation to invent client methods: pinned the adapter to the
  v8 client's `index()`, `search()`, `indices()->create()` shapes.
- Required the score conversion `cosine = 2 × _score − 1`, clamped to
  `[-1, 1]` to absorb floating-point drift — otherwise `RelevanceScore` would
  reject borderline values.
- Decided the projection runs off the `ProductWasIndexed` event, not inside
  the command handler (see DECISIONS #4), and added `search:reindex` to prove
  the read model is rebuildable from Postgres.
- Kept `num_candidates = max(100, 10 × limit)` as the documented recall knob.
