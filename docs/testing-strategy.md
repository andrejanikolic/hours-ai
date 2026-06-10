# Testing Strategy — HoursAI

This document defines **what** each test layer is responsible for. Follow it before adding new tests.

Aligned to the **serving-times** architecture (brands / venues / menus / order types). The legacy `/stores/{id}/hours` scaffold is P2.

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

**Current status:** PHPUnit coverage is largely built (Brand / Venue / Menu / OrderType / ServingTimes controller tests + `DeepSeekServingTimesParser` unit test). The `e2e/` Playwright layer is **not yet started** — it is the main outstanding gap.

---

## Ownership by task

| Task | Layer | What to prove |
|------|-------|---------------|
| **Parse endpoint** — `POST /api/serving-times/parse` | PHPUnit Feature | `preview` array for fixtures, `clarification_needed`, never 500 |
| **DeepSeek service** — `DeepSeekServingTimesParser` | PHPUnit Unit + optional live integration | Schema, partial updates, no hallucinated fields, markdown-fence stripping |
| **Vue component** — `HoursAIPrompt` | Playwright E2E | Textarea → Parse → preview → Apply / Edit |
| **Write-back** — `PUT /api/serving-times/replace` | PHPUnit Feature | DB state after replace, overlap 422, transactional rollback |
| **Entity CRUD** — brands / venues / menus / order types | PHPUnit Feature | GET / POST / PUT / DELETE, nesting, attach/detach |

---

## PHPUnit — backend rules

**Test here:**

- `POST /api/serving-times/parse` response shape (`preview`, `should_update`, `clarification_needed`, `clarification_message`)
- `PUT /api/serving-times/replace` persistence, overlap validation, atomic rollback
- `serving-times` CRUD (`GET` / `POST` / `PUT` / `DELETE`)
- `DeepSeekServingTimesParser` with **mocked** HTTP client
- Entity APIs (brand / venue / menu / order-type)
- All 5 fixture prompts (as feature/unit tests)

**Do not test here:**

- Button labels, CSS, preview table layout
- Browser-specific behavior

**Commands:**

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter ServingTimesControllerTest
docker compose exec app php artisan test --filter DeepSeekServingTimesParserTest
```

**Fixture location:** `backend/tests/fixtures/serving-times/` (JSON or PHP arrays shared with E2E where possible).

---

## Playwright — E2E rules

**Test here:**

- Operator types prompt → clicks Parse → preview renders
- Apply sends `PUT /serving-times/replace` with the expected payload
- Success toast appears after Apply
- Edit returns to textarea without a replace call
- Clarification / error states render correctly
- Core navigation: pick brand → tab → venue/menu/order-type detail → prompt

**Do not test here:**

- DeepSeek prompt engineering quality
- Every API edge case (PHPUnit owns those — and they are already built)
- Duplicating all 5 prompts if PHPUnit already covers schema — use 2–3 representative UI flows

**Golden rule:** Mock `POST **/serving-times/parse` in Playwright. See [playwright-architecture.md](./playwright-architecture.md).

---

## DeepSeek / AI — three modes

| Mode | When | How |
|------|------|-----|
| **Mocked** | PHPUnit unit tests, all Playwright runs | Return fixture JSON |
| **Stubbed HTTP** | PHPUnit feature tests | `Http::fake()` or mock Guzzle |
| **Live API** | Manual pre-demo check only | Real `DEEPSEEK_API_KEY`, mark `@group integration`, skip in CI |

Never depend on live DeepSeek for:

- CI pipelines
- The demo test run
- Playwright `test` command

---

## Shared fixtures

Use the same JSON shapes in both layers:

```
e2e/fixtures/responses/serving-times/standard-week.json
backend/tests/fixtures/serving-times/standard-week.json   ← same content
```

The 5 demo prompts:

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
| **Backend** | PHPUnit (built), fixture JSON, `Http::fake()` for DeepSeek |
| **Frontend** | `data-testid` attributes in `HoursAIPrompt.vue` and nav/list elements |
| **QA / full-stack** | `e2e/` scaffold, Playwright config, mock helpers, green suite |

---

## Definition of done (testing)

- [x] PHPUnit green for serving-times parse / replace / CRUD + parser unit
- [x] PHPUnit green for entity APIs (brand / venue / menu / order-type)
- [ ] `data-testid` added to `HoursAIPrompt.vue`
- [ ] Playwright green for the core operator journey (5–6 specs)
- [ ] No live DeepSeek in `playwright test` or CI
- [ ] Demo can run `npx playwright test` and show green before pitch
