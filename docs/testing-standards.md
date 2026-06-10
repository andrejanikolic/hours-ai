# Testing Standards — HoursAI

Conventions for writing and reviewing tests. Applies to PHPUnit and Playwright.

Aligned to the **serving-times** architecture (brands / venues / menus / order types).

---

## General principles

1. **One assertion focus per test** — test one behavior; multiple `expect` calls for the same behavior are fine.
2. **Arrange → Act → Assert** — keep this order visible in every test.
3. **No sleeps** — use Playwright auto-waiting or PHPUnit's synchronous assertions.
4. **Fixture over inline** — reuse JSON from `fixtures/` directories.
5. **Readable names** — test name describes behavior, not implementation.

---

## Naming conventions

### PHPUnit

```
tests/Feature/ServingTimesControllerTest.php
tests/Feature/BrandControllerTest.php
tests/Feature/VenueControllerTest.php
tests/Feature/MenuControllerTest.php
tests/Feature/OrderTypeControllerTest.php
tests/Unit/DeepSeekServingTimesParserTest.php
```

Method names (descriptive):

```php
public function test_parse_returns_preview_for_standard_week_prompt(): void
public function test_parse_returns_clarification_for_ambiguous_input(): void
public function test_replace_removes_existing_and_inserts_new(): void
public function test_replace_rejects_overlapping_days_within_batch(): void
public function test_replace_is_atomic_and_only_affects_given_parent(): void
```

### Playwright

```
e2e/tests/brand-serving-times-ai.spec.ts
e2e/tests/brand-serving-times-clarification.spec.ts
```

Test titles — behavior in plain English:

```ts
test('shows preview rows with closed Sunday after parse', ...)
test('sends PUT /serving-times/replace when Apply is clicked', ...)
test('does not call replace when Edit is clicked', ...)
```

---

## Selectors (Playwright)

**Priority order:**

1. `data-testid` — primary for HoursAI
2. `getByRole` — when testid not yet added (button name, heading)
3. **Never** — CSS classes, Vue scoped attributes, DOM structure

```ts
// Good
page.getByTestId('hours-parse-btn')
page.getByRole('button', { name: 'Parse' })

// Bad
page.locator('.btn-apply')
page.locator('div.preview > table > tbody > tr:nth-child(1)')
```

`AppButton`'s template root is the `<button>` element, so a `data-testid` set on `<AppButton data-testid="…">` falls through onto the button.

### Required `data-testid` values

| UI | testid |
|----|--------|
| Prompt textarea | `hours-prompt-input` |
| Parse button | `hours-parse-btn` |
| Preview container | `hours-preview` |
| Apply button | `hours-apply-btn` |
| Edit button | `hours-edit-btn` |
| Success toast | `hours-success` |
| Parse/apply error banner | `hours-error` |
| Clarification banner | `hours-clarification` |
| Manual serving-times list (`ServingTimesPanel`) | `serving-times-list` |

Add testids in the same PR as the first E2E spec that needs them.

---

## Fixture format

### Parse response JSON

Must match what `ServingTimesController::parse` returns:

```json
{
  "preview": [
    {
      "parent_type": "brand",
      "parent_id": 1,
      "type": "weekday",
      "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
      "date": null,
      "date_to": null,
      "time_from": "08:00",
      "time_to": "22:00",
      "working": true
    }
  ],
  "should_update": true,
  "clarification_needed": false,
  "clarification_message": null
}
```

Rules:

- `preview` is an array of serving-time rows; top-level keys are `preview`, `should_update`, `clarification_needed`, `clarification_message`.
- `type` is `weekday` or `special`.
- **Weekday rows:** `days` is a non-empty array of lowercase full English names (`monday`, not `Mon`); `date` / `date_to` are `null`.
- **Special rows:** `date` (and optional `date_to`) in `Y-m-d`; `days` is `null`.
- Times in `time_from` / `time_to` use `HH:MM` 24-hour format.
- A closed period → `working: false` (times may be `null`).
- Clarification path → `clarification_needed: true`, `clarification_message` set, `preview` empty, `should_update: false`.

### Prompt fixtures

```ts
// e2e/fixtures/prompts.ts
export const STANDARD_WEEK_PROMPT =
  "We're open Mon–Fri 8am to 10pm, Saturday 9am to 11pm, and closed Sundays.";

export const CHRISTMAS_CLOSURE_PROMPT =
  'Close all online ordering on Christmas Day and New Year\'s Day.';
```

Keep prompt strings identical to the demo prompts in [test-plan.md](./test-plan.md).

---

## PHPUnit standards

### Mock external HTTP

```php
Http::fake([
    'api.deepseek.com/*' => Http::response($fixtureJson, 200),
]);
```

Never call live DeepSeek in default test runs. Mark live tests:

```php
/**
 * @group integration
 */
public function test_live_deepseek_standard_week(): void
{
    if (! env('DEEPSEEK_API_KEY')) {
        $this->markTestSkipped('DEEPSEEK_API_KEY not set');
    }
    // ...
}
```

### Database

- Use `RefreshDatabase` or `DatabaseTransactions` in feature tests
- Seed data is created by `DatabaseSeeder`: brands Demo Burger (id 1), Pasta House (id 2), Starbird (id 3); the Demo Burger brand parent has a seeded weekday serving-time row
- Assert DB state via Eloquent or `assertDatabaseHas`

### Assertions

- Assert HTTP status first, then JSON shape, then specific values
- Use `assertJsonStructure` for schema checks
- Use `assertJsonPath` for nested fields (e.g. `assertJsonPath('0.type', 'weekday')`)

---

## Playwright standards

### Setup per test

```ts
test.beforeEach(async ({ page }) => {
  await mockParseSuccess(page);  // default happy path
});
```

Override mocks inside individual tests when needed.

### Network assertions

Prefer `waitForRequest` / `waitForResponse` over inspecting `fetch` in the browser:

```ts
const response = await page.waitForResponse(
  (res) => res.url().includes('/serving-times/parse') && res.status() === 200,
);
const body = await response.json();
expect(Array.isArray(body.preview)).toBe(true);
```

### Files and artifacts

- Traces: `on-first-retry` (config default)
- Screenshots: `only-on-failure`
- Add `playwright-report/` and `test-results/` to `.gitignore`

---

## Review checklist

Before merging test PRs:

- [ ] Does this test belong in the right layer? (see [testing-strategy.md](./testing-strategy.md))
- [ ] Playwright tests mock the `/serving-times/parse` endpoint?
- [ ] `data-testid` used instead of CSS selectors?
- [ ] Fixture JSON matches the `preview` parse schema?
- [ ] Test name describes user-visible or API behavior?
- [ ] No `waitForTimeout` / `sleep()`?
- [ ] PHPUnit live integration tests marked `@group integration`?

---

## Commands reference

```bash
# Backend
docker compose exec app php artisan test
docker compose exec app php artisan test --filter ServingTimesControllerTest
docker compose exec app php artisan test --group=integration

# E2E (backend + frontend must be running)
cd e2e && npx playwright test
cd e2e && npx playwright test --ui
cd e2e && npx playwright test brand-serving-times-ai.spec.ts

# Reset DB before E2E suite
docker compose exec app php artisan migrate:fresh --seed
```
