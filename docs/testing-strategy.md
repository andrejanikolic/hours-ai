# Testing Strategy — HoursAI

This document defines **what** each test layer is responsible for. Follow it before adding new tests.

---

## Testing pyramid

```
        ┌─────────────┐
        │  Manual     │  Live DeepSeek — 5 demo prompts, skipped in CI
        ├─────────────┤
        │ Playwright  │  5–6 E2E specs — UI flow, thin layer
        ├─────────────┤
        │  PHPUnit    │  Most coverage — API, service, DB
        └─────────────┘
```

| Layer | Location | Speed | Deterministic |
|-------|----------|-------|---------------|
| PHPUnit | `backend/tests/` | Fast | Yes |
| Playwright | `e2e/tests/` | Slower | Yes (with mocks) |
| Live DeepSeek | Manual / optional integration test | Slow | No |

---

## Ownership by task

Maps to [README.md](../README.md) atomic tasks.

| Task | Layer | What to prove |
|------|-------|---------------|
| **Task 1** — Parse endpoint | PHPUnit Feature | Valid JSON for fixtures, `clarification_needed`, no 500 |
| **Task 2** — DeepSeek service | PHPUnit Unit + optional live integration | Schema, partial updates, no hallucinated fields |
| **Task 3** — Vue component | Playwright E2E | Textarea → Parse → preview → Apply/Edit |
| **Task 4** — Write-back | PHPUnit Feature | DB state after PATCH, rollback on 422 |

---

## PHPUnit — backend rules

**Test here:**

- `POST /api/stores/{id}/hours/parse` response shape
- `PATCH /api/stores/{id}/hours` persistence and validation
- `DeepSeekHoursParser` with **mocked** HTTP client
- Transaction rollback on validation errors
- All 5 fixture prompts (as feature/unit tests)

**Do not test here:**

- Button labels, CSS, preview table layout
- Browser-specific behavior

**Commands:**

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter StoreHoursParseTest
```

**Fixture location:** `backend/tests/fixtures/` (JSON or PHP arrays shared with E2E where possible).

---

## Playwright — E2E rules

**Test here:**

- Operator types prompt → clicks Parse → preview renders
- Apply sends `PATCH` with expected payload
- Success toast appears after Apply
- Edit returns to textarea without PATCH
- Clarification / error states render correctly

**Do not test here:**

- DeepSeek prompt engineering quality
- Every API edge case (PHPUnit owns those)
- Duplicate all 5 prompts if PHPUnit already covers schema — use 2–3 representative UI flows

**Golden rule:** Mock `POST .../hours/parse` in Playwright. See [playwright-architecture.md](./playwright-architecture.md).

---

## DeepSeek / AI — three modes

| Mode | When | How |
|------|------|-----|
| **Mocked** | PHPUnit unit tests, all Playwright runs | Return fixture JSON |
| **Stubbed HTTP** | PHPUnit feature tests | `Http::fake()` or mock Guzzle |
| **Live API** | Manual pre-demo check only | Real `DEEPSEEK_API_KEY`, mark `@group integration`, skip in CI |

Never depend on live DeepSeek for:

- CI pipelines
- Thursday demo test run
- Playwright `test` command

---

## Shared fixtures

Use the same JSON shapes in both layers:

```
e2e/fixtures/responses/standard-week.json
backend/tests/fixtures/standard-week.json   ← same content
```

The 5 demo prompts from README:

1. Mon–Fri 8am–10pm, Sat 9am–11pm, closed Sundays
2. Close all online ordering on Christmas and New Year's
3. 15-minute pickup buffer on weekends only
4. Stop orders 30 minutes before closing
5. Delivery 11am–9pm, pickup until close

PHPUnit validates parsing logic against all 5. Playwright uses 2–3 mocked responses to prove UI behavior.

---

## Team split

| Role | Owns |
|------|------|
| **Backend** | PHPUnit, fixture JSON, `Http::fake()` for DeepSeek |
| **Frontend** | `data-testid` attributes in `StoreHoursNaturalInput.vue` |
| **QA / full-stack** | `e2e/` scaffold, Playwright config, mock helpers, green suite |

---

## Definition of done (testing)

- [ ] PHPUnit green for Tasks 1, 2, 4
- [ ] Playwright green for Task 3 harness (5–6 specs)
- [ ] No live DeepSeek in `playwright test` or CI
- [ ] Demo can run `npx playwright test` and show green before pitch
