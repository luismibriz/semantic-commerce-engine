# DECISIONS.md — Where I overruled the AI

A short log of moments where the assistant proposed one thing and I decided
otherwise, with the reasoning. The point is to show direction, not delegation.

---

### 1. Vector store: pgvector → Postgres + Elasticsearch

**AI proposed:** PostgreSQL with the `pgvector` extension as a single store
for products and vectors — "simplest, one datastore".

**I decided:** Postgres as the write model / source of truth **and**
Elasticsearch as a separate read store for semantic search (`dense_vector` +
`knn`).

**Why:** pgvector would have worked, but it collapses the read and write
models into one store and one shape. A separate read store is a far stronger,
honest demonstration of CQRS, and Elasticsearch is a natural fit for a
discovery-heavy system. The extra container is worth it; the cost (eventual
consistency) is real and is documented, not hidden.

---

### 2. Money modelled as `float`

**AI proposed:** a `price` as `float` on the product, with a `currency`
string alongside.

**I decided:** a `Money` value object holding an **integer of minor units**
plus an ISO-4217 currency, rejecting floats and negative amounts.

**Why:** binary floating point cannot represent decimal money exactly; any
sum or comparison drifts. This is a textbook defect. Money is an
invariant-bearing value object, not two loose scalars.

---

### 3. Tests against a mocked OpenAI client

**AI proposed:** mock the OpenAI HTTP client in the use-case tests so they run
without network.

**I decided:** keep OpenAI behind the `EmbeddingGenerator` port and write a
real second adapter — `HashingEmbeddingGenerator` (hashing-trick embeddings,
fully deterministic). Tests exercise that real adapter; search-ranking tests
use a `PredefinedEmbeddingGenerator` double with hand-crafted vectors.

**Why:** a mock that returns whatever the test tells it proves nothing — it is
a "test that doesn't test anything". A deterministic real adapter exercises
actual code, and as a bonus lets the whole stack boot with `docker compose up`
without any API key.

---

### 4. Projecting to Elasticsearch inside the command handler

**AI proposed:** in `IndexProductHandler`, after saving the product, call the
Elasticsearch adapter directly to index the document.

**I decided:** the handler records a `ProductWasIndexed` domain event; a
separate projector (`ProjectProductToSearchIndex`) subscribes to it and writes
to Elasticsearch.

**Why:** the write-side use case must not know a read store exists — that is
the CQRS seam. The event-driven projection also makes the read model rebuildable
(`search:reindex`). I accepted that the synchronous projection is not
transactional and documented the outbox pattern as the production answer,
rather than pretending the problem away.

---

### 5. ORM attributes on the `Product` entity

**AI proposed:** annotate `Product` and its value objects with Doctrine
`#[ORM\Entity]`, `#[ORM\Column]`, etc. — "the standard Symfony way".

**I decided:** the entity stays pure PHP. Doctrine maps it through XML files
in `config/doctrine/` plus custom DBAL types for each value object.

**Why:** the domain must stay decoupled from the framework. ORM attributes are
a framework dependency compiled into the domain class. XML mapping keeps that
knowledge in the infrastructure layer where it belongs.

---

### 6. Suppressing PHPStan with `array_values()` and inline `@var`

**AI proposed:** when PHPStan flagged type issues at the JSON/API boundary, the
generated code reached for `array_values()` calls and an inline
`/** @var list<float|int> */` to make the analyser happy.

**I decided:** remove both. Widen the `EmbeddingVector` constructor's `@param`
to `array<array-key, mixed>` — its true contract — and add an explicit
`is_array()` guard when decoding JSON.

**Why:** `EmbeddingVector` sits at a trust boundary; it must validate arbitrary
input. A narrow phpdoc made its own validation look like dead code, and the
inline `@var` was lying to the analyser. The fix is to tell the truth about
the boundary, not to silence the tool.
