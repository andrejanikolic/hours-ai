# Testing Standards — HoursAI

Conventions for writing and reviewing tests. Applies to PHPUnit and Playwright.

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
tests/Feature/StoreHoursParseTest.php
tests/Feature/StoreHoursUpdateTest.php
tests/Unit/DeepSeekHoursParserTest.php
```

Method names (PHPUnit 11 style or descriptive):

```php
public function test_parse_returns_valid_schema_for_standard_week_prompt(): void
public function test_parse_returns_clarification_for_ambiguous_input(): void
public function test_update_persists_hours_to_database(): void
public function test_update_rolls_back_on_validation_error(): void
```

### Playwright

```
e2e/tests/parse-and-preview.spec.ts
e2e/tests/apply-hours.spec.ts
```

Test titles — behavior in plain English:

```ts
test('shows preview table with closed Sunday after parse', ...)
test('sends PATCH with seven days when Apply is clicked', ...)
test('does not call PATCH when Edit is clicked', ...)
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

### Required `data-testid` values

| UI | testid |
|----|--------|
| Hours textarea | `hours-input` |
| Parse button | `hours-parse-btn` |
| Preview section | `hours-preview` |
| Apply button | `hours-apply-btn` |
| Edit button | `hours-edit-btn` |
| Success banner | `hours-success` |
| Error banner | `hours-error` |
| Clarification banner | `hours-clarification` |

Add testids in the same PR as the first E2E spec that needs them.

---

## Fixture format

### Parse response JSON

Must match the API schema from [README.md](../README.md):

```json
{
  "days": [
    { "day": "monday", "open": "08:00", "close": "22:00", "closed": false }
  ],
  "specialClosures": [],
  "orderCutoffMinutes": null,
  "deliveryWindow": null,
  "pickupWindow": null,
  "clarification_needed": false
}
```

Rules:

- All 7 days always present in `days`
- Times in `HH:MM` 24-hour format
- Day names lowercase full English (`monday`, not `Mon`)
- `closed: true` → `open` and `close` may be `null`

### Prompt fixtures

```ts
// e2e/fixtures/prompts.ts
export const STANDARD_WEEK_PROMPT =
  "We're open Mon–Fri 8am to 10pm, Saturday 9am to 11pm, and closed Sundays.";

export const CHRISTMAS_CLOSURE_PROMPT =
  'Close all online ordering on Christmas Day and New Year\'s Day.';
```

Keep prompt strings identical to README demo prompts.

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
- Seed store `id = 1` for consistency with frontend `App.vue`
- Assert DB state via Eloquent or `assertDatabaseHas`

### Assertions

- Assert HTTP status first, then JSON shape, then specific values
- Use `assertJsonStructure` for schema checks
- Use `assertJsonPath` for nested fields

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
  (res) => res.url().includes('/hours/parse') && res.status() === 200,
);
const body = await response.json();
expect(body.days).toHaveLength(7);
```

### Files and artifacts

- Traces: `on-first-retry` (config default)
- Screenshots: `only-on-failure`
- Commit `playwright-report/` and `test-results/` to **gitignore**

---

## Review checklist

Before merging test PRs:

- [ ] Does this test belong in the right layer? (see [testing-strategy.md](./testing-strategy.md))
- [ ] Playwright tests mock DeepSeek / parse endpoint?
- [ ] `data-testid` used instead of CSS selectors?
- [ ] Fixture JSON matches API schema?
- [ ] Test name describes user-visible or API behavior?
- [ ] No `waitForTimeout` / `sleep()`?
- [ ] PHPUnit live integration tests marked `@group integration`?

---

## Commands reference

```bash
# Backend
docker compose exec app php artisan test
docker compose exec app php artisan test --group=integration

# E2E (backend + frontend must be running)
cd e2e && npx playwright test
cd e2e && npx playwright test --ui
cd e2e && npx playwright test apply-hours.spec.ts

# Reset DB before E2E suite
docker compose exec app php artisan migrate:fresh --seed
```
