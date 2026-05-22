# Prompt 1 — Domain-first bootstrap

**Goal:** start from the domain, not the framework.

## Prompt given

> Build the Semantic Discovery Engine with Symfony, but generate the
> **domain layer first** and in complete isolation. No Symfony,
> no Doctrine, no Elasticsearch imports in `src/Catalog/Domain` — only PHP
> built-ins and other domain classes.
>
> Model the `Catalog` context: a `Product` aggregate, value objects
> (`ProductId`, `ProductName`, `ProductDescription`, `Money`, `EmbeddingVector`,
> `SearchQuery`, `SearchLimit`, `RelevanceScore`), the `ProductWasIndexed`
> event, and the ports (`ProductRepository`, `EmbeddingGenerator`, `Clock`,
> `SemanticProductSearch`). Value objects must be immutable and self-validating.
> `Money` is an integer of minor units — never a float.

## How I steered it

- Rejected the first draft's `float $price`; required a `Money` value object
  (see DECISIONS #2).
- Required `EmbeddingVector` to be opaque to the domain: the domain compares
  whole vectors (cosine similarity) and never inspects components.
- Made `searchableText()` a method on the aggregate so indexing and querying
  can never feed the model divergent text.
- Verified afterwards with `grep` that `src/Catalog/Domain` imports nothing
  outside `App\Catalog\Domain\*`.
