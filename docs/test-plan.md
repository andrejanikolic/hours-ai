# Automation Test Plan — HoursAI

What to cover with **PHPUnit** (backend) and **Playwright** (E2E), aligned with the current codebase and hackathon submission.

Related: [testing-strategy.md](./testing-strategy.md) · [playwright-architecture.md](./playwright-architecture.md) · [API.md](../API.md) · [UI.md](../UI.md)

---

## Current scope

The app has two API surfaces:

| Area | Status | Test priority |
|------|--------|---------------|
| **Serving times** (brands / venues / menus / order types) | Primary — full UI + AI flow | **P0** |
| **Entity CRUD** (brands, venues, menus, order types) | Supporting MC navigation | **P1** |
| **Legacy store hours** (`/stores/{id}/hours`) | Original scaffold, less UI | **P2** |
| **DeepSeek test page** (`/test`) | Dev/QA tool | **P1** (smoke only) |

**Today:** the PHPUnit layer is largely built — `ServingTimesControllerTest`, `BrandControllerTest`, `VenueControllerTest`, `MenuControllerTest`, `OrderTypeControllerTest`, and the `DeepSeekServingTimesParserTest` unit test all exist. The **Playwright `e2e/` layer is not yet started** and is the main outstanding gap (no config, no specs, and no `data-testid` attributes in `frontend/src` yet).

---

## Testing pyramid

```
┌─────────────────────────────────────────┐
│ Manual — live DeepSeek, 5 demo prompts  │  pre-pitch only
├─────────────────────────────────────────┤
│ Playwright — 8–12 E2E specs             │  operator journeys
├─────────────────────────────────────────┤
│ PHPUnit — ~25–35 tests                  │  API, validation, DB, parser
└─────────────────────────────────────────┘
```

**Golden rule:** Mock DeepSeek in PHPUnit (`Http::fake`) and Playwright (`page.route`). Live API only in `@group integration` tests, skipped in CI.

---

## Shared fixtures (5 demo prompts)

Use the same strings everywhere — from hackathon submission:

| # | Prompt | Expected signal |
|---|--------|-----------------|
| 1 | We're open Mon–Fri 8am to 10pm, Saturday 9am to 11pm, closed Sundays. | Weekday slots + Sunday `working: false` |
| 2 | Close all online ordering on Christmas Day and New Year's Day. | `special` entries for two dates |
| 3 | Add a 15-minute pickup buffer on weekends only. | Weekend-only change (partial update) |
| 4 | We stop taking orders 30 minutes before closing every day. | Clarification or metadata — document actual behaviour |
| 5 | Enable delivery only between 11am and 9pm, keep pickup available until close. | Split windows / clarification |

Store mocked JSON under:

```
backend/tests/fixtures/serving-times/
e2e/fixtures/responses/serving-times/
```

---

## P0 — Must have for demo (build first)

### PHPUnit — Serving times AI + write-back

| Test file | Cases |
|-----------|-------|
| `ServingTimesParseTest` | `POST /serving-times/parse` returns 200 + `preview` array for fixture 1 (mocked DeepSeek) |
| | `clarification_needed: true` → 200, never 500 |
| | Missing `prompt` / invalid `parent_type` → 422 |
| | Unknown `parent_id` → empty current hours still parses |
| `ServingTimesReplaceTest` | `PUT /serving-times/replace` persists rows for brand parent |
| | GET after replace returns same slots |
| | **Overlap** — two weekday entries sharing `monday` → 422 |
| | Replace is transactional — failed insert leaves DB unchanged |
| `DeepSeekServingTimesParserTest` (unit) | Strips markdown fences from model output |
| | Strips keys outside schema |
| | Partial update preserves unchanged slots from injected current hours |
| | `clarification_needed` path |

### Playwright — Core operator journey

| Spec | Flow |
|------|------|
| `brand-serving-times-ai.spec.ts` | `/` (pick brand from sidebar) → brand detail → open a tab → drill into a venue/menu/order-type detail (where `HoursAIPrompt` renders) → type prompt → Parse → preview rows visible |
| | Apply → success toast → manual list refreshes |
| | Edit prompt → preview dismissed, no replace call |
| `brand-serving-times-clarification.spec.ts` | Mock `clarification_needed` → clarification banner, no Apply |

**Mock:** `POST **/serving-times/parse` in every Playwright test. **Apply** issues `PUT **/serving-times/replace`.

> **Note on flow:** there is no standalone "Serving Times tab." `HoursAIPrompt` renders inside `VenueDetailPage` (`/brands/:b/venues/:v`), `MenuDetailPage` (`/brands/:b/menus/:m`), and `OrderTypeCard` (Order Types tab). The brand tabs are *Venue Opening Hours*, *Menu Serving Times*, and *Order Types Delivery Times*. The fastest E2E entry is to navigate directly to a seeded venue/menu URL.

---

## P1 — Full feature coverage

### PHPUnit — Serving times CRUD  ✅ built (`ServingTimesControllerTest`)

| Test file | Cases |
|-----------|-------|
| `ServingTimesControllerTest` | `GET /serving-times?parent_type&parent_id` lists seeded slots |
| | `POST /serving-times` creates weekday entry → 201 |
| | `POST` overlapping weekday → 422 with day names in message |
| | `PUT /serving-times/{id}` updates times; `DELETE /serving-times/{id}` → 204 |
| | `PUT /serving-times/replace` removes + inserts, atomic, overlap 422 |

### PHPUnit — Entity APIs  ✅ built

| Test file | Cases |
|-----------|-------|
| `BrandControllerTest` | GET list, POST create, PUT update, DELETE |
| `VenueControllerTest` | Nested under brand; GET/PUT/DELETE |
| `MenuControllerTest` | Nested under brand |
| `OrderTypeControllerTest` | Global list; attach/detach on venue |

### PHPUnit — Integration (optional, skip in CI)

| Test file | Cases |
|-----------|-------|
| `DeepSeekServingTimesIntegrationTest` | `@group integration` — all 5 fixtures against live API when `DEEPSEEK_API_KEY` set |

### Playwright — Navigation + panels

| Spec | Flow |
|------|------|
| `brand-nav.spec.ts` | `/` welcome → pick a brand from sidebar → `/brands/:id` loads with tabs |
| `brand-venues-menus.spec.ts` | Brand detail tabs (*Venue Opening Hours*, *Menu Serving Times*): add venue, add menu (inline forms) |
| `venue-order-types.spec.ts` | *Order Types Delivery Times* tab loads cards |
| `manual-serving-time.spec.ts` | Add weekday slot via `AddServingTimeForm` → appears in `ServingTimesPanel` list |
| `deepseek-test-page.spec.ts` | `/test` — select row, parse (mocked), preview renders |

---

## P2 — Legacy + edge cases

### PHPUnit — Legacy store hours

| Test file | Cases |
|-----------|-------|
| `StoreHoursParseTest` | `POST /stores/1/hours/parse` schema (if still used) |
| `StoreHoursUpdateTest` | `PATCH /stores/1/hours` DB write |

### Playwright — Error states

| Spec | Flow |
|------|------|
| `api-error.spec.ts` | Mock 500 on parse → error message in `HoursAIPrompt` |
| `overlap-apply.spec.ts` | Mock parse OK, real replace 422 → error shown |

---

## What each layer owns

| Concern | PHPUnit | Playwright |
|---------|---------|------------|
| Parse JSON schema | ✅ | ❌ |
| Overlap validation | ✅ | ❌ |
| DB state after replace | ✅ | optional 1 spec |
| Preview table rendering | ❌ | ✅ |
| Toast on apply | ❌ | ✅ |
| Edit without write | ❌ | ✅ |
| Tab navigation | ❌ | ✅ |
| DeepSeek prompt quality | manual / integration | ❌ |
| Button CSS | ❌ | ❌ |

---

## `data-testid` checklist (frontend prerequisite)

Add before writing Playwright specs:

| Component | testid |
|-----------|--------|
| `HoursAIPrompt` textarea | `hours-prompt-input` |
| Parse button | `hours-parse-btn` |
| Preview container | `hours-preview` |
| Apply button | `hours-apply-btn` |
| Edit prompt button | `hours-edit-btn` |
| Clarification banner | `hours-clarification` |
| Parse/apply error banner | `hours-error` |
| Success toast | `hours-success` |
| `ServingTimesPanel` manual list | `serving-times-list` |
| Sidebar brand link (`WelcomePage`) | `brand-link-{id}` |
| Brand detail tab (`TabBar`) | `tab-{venues\|menus\|order-types}` |

---

## Implementation order (suggested)

```
✅ Done — PHPUnit
  ├── ServingTimesControllerTest (parse, replace, CRUD, overlap)
  ├── DeepSeekServingTimesParserTest (unit)
  └── Brand / Venue / Menu / OrderType controller tests

Next — frontend prerequisite
  └── Add data-testid to HoursAIPrompt (+ sidebar brand links, tabs, manual list)

Then — Playwright (the outstanding gap)
  ├── e2e/ scaffold: playwright.config.ts, api-mocks.ts, serving-times.page.ts
  ├── brand-serving-times-ai.spec.ts (mocked parse → preview → apply)
  └── clarification + edit + error specs

Optional / pre-pitch
  ├── Playwright: navigation + manual serving-time specs
  └── Manual: live DeepSeek × 5 prompts

Before pitch
  └── Run full suite green; demo path matches P0 Playwright spec
```

---

## Team split

| Person | Owns |
|--------|------|
| **Backend** | PHPUnit P0 + P1, fixture JSON, `Http::fake()` |
| **Frontend** | `data-testid` attributes |
| **QA / full-stack** | `e2e/` scaffold, Playwright config, mock helpers, CI script |

---

## Commands

```bash
# Backend
docker compose exec app php artisan test
docker compose exec app php artisan test --filter ServingTimesControllerTest
docker compose exec app php artisan test --group=integration

# E2E (after scaffold)
cd e2e && npx playwright test
cd e2e && npx playwright test brand-serving-times-ai.spec.ts

# Reset DB before E2E
docker compose exec app php artisan migrate:fresh --seed
```

---

## Definition of done

- [x] P0 PHPUnit green (parse, replace, overlap, parser unit)
- [x] P1 PHPUnit green (entity APIs: brand / venue / menu / order-type)
- [ ] `data-testid` added to `HoursAIPrompt` (+ nav / list hooks)
- [ ] P0 Playwright green (parse → preview → apply → toast)
- [ ] No live DeepSeek in CI or `playwright test`
- [ ] Fixture JSON shared between PHPUnit and Playwright
- [ ] Demo path: pick brand → venue/menu detail → fixture 1 prompt → Parse → Apply
- [ ] Optional: P1 navigation + manual serving-time specs green

---

## Explicitly out of scope

- Auth / permissions
- Visual regression / screenshot diff
- Performance / load tests
- Mobile viewport (unless time permits)
- Testing Vue composables in isolation (covered by E2E + API)
