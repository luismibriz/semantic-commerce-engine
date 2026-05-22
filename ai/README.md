# ai/ — Design and AI-assisted development

This project was built with an AI coding assistant under human direction. This
folder documents both the **design** and the **process**: what was specified,
which decisions were made against the assistant's suggestions, and how the
work was steered.

## Contents

| File | What it is |
|------|------------|
| [`PLAN.md`](PLAN.md) | Functional and technical specification — business rules, domain model, use-case flows (happy and sad paths), and the architectural constraints the implementation must respect. Written to be executable by an AI agent. |
| [`DECISIONS.md`](DECISIONS.md) | A log of moments where the assistant proposed one thing and a different decision was made on senior judgement, with the reasoning. Direction, not delegation. |
| [`prompts/`](prompts/) | The key prompts that drove the build, each with a note on how the output was steered and corrected. |
| [`agents/`](agents/) | A custom agent definition — an architecture reviewer that guards the hexagonal/DDD/CQRS boundaries. |

The project rules that keep generated code coherent live in
[`../CLAUDE.md`](../CLAUDE.md) at the repository root.

## How to read it

Start with `PLAN.md` to understand *what* was built and *why* the boundaries
are where they are. Then `DECISIONS.md` shows where generic AI output was
overridden — that is the most honest signal of the judgement applied. The
commit history mirrors the same trace: commit bodies state, where relevant,
what the assistant proposed and why it was rejected.
