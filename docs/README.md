# HoursAI — Testing Documentation

Rules and architecture for automated testing on this project. Aligned to the **serving-times** architecture (brands / venues / menus / order types).

| Document | Purpose |
|----------|---------|
| [testing-strategy.md](./testing-strategy.md) | What to test where — PHPUnit vs Playwright vs manual |
| [playwright-architecture.md](./playwright-architecture.md) | E2E folder layout, config, mocks, and test cases |
| [testing-standards.md](./testing-standards.md) | Naming, selectors, fixtures, and team conventions |
| [test-plan.md](./test-plan.md) | Full automation coverage plan (PHPUnit + Playwright) |

## Quick rules

1. **PHPUnit** owns API correctness, schema, and DB writes.
2. **Playwright** owns the operator UI journey (type → parse → preview → apply).
3. **Never call live DeepSeek in Playwright** — mock parse responses with fixtures.
4. Add `data-testid` to interactive Vue elements before writing E2E specs.

## Related

- [README.md](../README.md) — product spec and acceptance criteria
- [SETUP.md](../SETUP.md) — local dev and run commands
