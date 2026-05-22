# Prompt 2 — CQRS use cases and Messenger buses

**Goal:** a clean write/read split with framework-agnostic application code.

## Prompt given

> Implement the application layer as CQRS. Two use cases: `IndexProduct`
> (command) and `SearchProducts` (query). Define `CommandBus`, `QueryBus` and
> `DomainEventBus` as **interfaces in the application layer**. The handlers are
> plain invokable classes that depend only on domain ports — no Symfony
> attributes, no Messenger types.
>
> In infrastructure, back each bus with a dedicated Symfony Messenger bus
> (`command.bus`, `query.bus`, `event.bus`) and adapters that implement the
> application interfaces. Register handlers via `services.yaml` tags, not
> `#[AsMessageHandler]`, so the application layer stays framework-free.

## How I steered it

- Rejected `#[AsMessageHandler]` on the handlers — it would couple the
  application layer to Symfony. Used `services.yaml` tags instead.
- Required commands to return `void` and queries to return read-model DTOs
  (`SearchProductsResponse`), never entities.
- Decided indexing must generate the embedding **before** persisting, so a
  provider failure leaves no half-indexed product.
- Verified with `php bin/console debug:messenger` that each handler is bound
  to the correct bus.
