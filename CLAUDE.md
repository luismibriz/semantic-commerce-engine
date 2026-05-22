# CLAUDE.md — Project rules for AI agents

Semantic Discovery Engine — an intent-based product search service. These
rules are non-negotiable: an agent must follow them when generating or
modifying code in this repository.

## What this project is

An intent-based product search engine. Products are indexed (an embedding is
generated for each), and a query in natural language returns products ranked
by semantic relevance — not by keyword matching.

## Architecture — Hexagonal + DDD + CQRS

Three layers under `src/Catalog/`, dependencies point **inwards only**:

- `Domain/` — entities, value objects, aggregates, domain events, and **ports**
  (interfaces). Pure PHP. **MUST NOT import Symfony, Doctrine, Elasticsearch or
  any other framework/library.** Only `App\Catalog\Domain\*` and PHP built-ins.
- `Application/` — use cases (command/query handlers), bus interfaces, read
  models. Depends on `Domain/` only. **No framework imports.**
- `Infrastructure/` — adapters: Doctrine, Elasticsearch, Symfony HTTP/Console,
  Messenger, OpenAI. This is the **only** layer allowed to touch frameworks.

Hard rules:

1. The domain never depends on the application or the infrastructure.
2. A new external dependency is wired through a **port** in `Domain/` (or
   `Application/` for read-side ports) and an **adapter** in `Infrastructure/`.
3. Persistence mapping stays out of the entity: Doctrine uses XML mapping in
   `config/doctrine/`. No ORM attributes in `src/Catalog/Domain/`.
4. Write side (commands) and read side (queries) are separate. Commands return
   nothing; queries never mutate state.
5. Money is an integer of minor units. **Never use `float` for money.**
6. Validation lives in value objects / the domain, not in controllers.

## Conventions

- PHP 8.3+, `declare(strict_types=1);` in every file.
- No `mixed`/`any` leaking out of the infrastructure boundary.
- Value objects are immutable and self-validating; invalid input throws
  `App\Catalog\Domain\Exception\InvalidArgument`.
- Tests: one behaviour per test, descriptive names, no logic in tests.
- Conventional Commits in English (`feat:`, `fix:`, `refactor:`, `test:`,
  `chore:`, `docs:`).

## Quality gate — run before every commit

```bash
composer quality   # php-cs-fixer (dry-run) + PHPStan + PHPUnit
```

All three must pass. PHPStan/CS warnings are defects, not noise: fix the cause,
never suppress with baselines or `@phpstan-ignore`.

## Commands

```bash
composer test                       # PHPUnit
composer phpstan                    # static analysis (level 6)
composer cs:fix                     # apply code style
php bin/console search:setup        # create the Elasticsearch index
php bin/console search:reindex      # rebuild the read model from Postgres
```

## Do not

- Do not couple the domain to a framework.
- Do not invent Elasticsearch/OpenAI API shapes — verify against the installed
  client version.
- Do not write tests that assert on mocks instead of behaviour.
- Do not push to remote branches without an explicit request.
