# Playwright Architecture — HoursAI

How to structure, configure, and run E2E tests for this project.

---

## Location

Playwright lives at **repo root**, not inside `frontend/`:

```
hours-ai/
├── backend/
├── frontend/
└── e2e/
    ├── playwright.config.ts
    ├── package.json              ← optional: e2e-only deps
    ├── fixtures/
    │   ├── prompts.ts
    │   └── responses/
    │       ├── standard-week.json
    │       ├── christmas-closure.json
    │       ├── clarification.json
    │       └── server-error.json
    ├── pages/
    │   └── store-hours.page.ts
    ├── helpers/
    │   ├── api-mocks.ts
    │   └── db-reset.ts
    └── tests/
        ├── parse-and-preview.spec.ts
        ├── apply-hours.spec.ts
        ├── clarification-flow.spec.ts
        ├── edit-without-apply.spec.ts
        └── error-state.spec.ts
```

**Why root-level `e2e/`?**

- Orchestrates frontend + backend without coupling to Vite internals
- Clear ownership for QA / full-stack teammate
- Config can wait for Docker health check + Vite dev server

---

## Install

```bash
cd e2e
npm init -y
npm install -D @playwright/test
npx playwright install chromium
```

Root `package.json` scripts (optional):

```json
{
  "scripts": {
    "test:e2e": "cd e2e && npx playwright test",
    "test:e2e:ui": "cd e2e && npx playwright test --ui",
    "test:e2e:report": "cd e2e && npx playwright show-report"
  }
}
```

---

## Configuration

`e2e/playwright.config.ts`:

```ts
import { defineConfig, devices } from '@playwright/test';

const FRONTEND_URL = process.env.FRONTEND_URL ?? 'http://localhost:5173';
const API_URL = process.env.API_URL ?? 'http://localhost:8001/api';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,           // single seeded store — avoid DB races
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['html'], ['list']],
  use: {
    baseURL: FRONTEND_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: {
    command: 'npm run dev',
    cwd: '../frontend',
    url: FRONTEND_URL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});

export { API_URL };
```

**Port note:** If `g360` uses 8000/3306, HoursAI backend may run on **8001**. Set `VITE_API_BASE_URL=http://localhost:8001/api` in `frontend/.env.local` before E2E.

**Prerequisites before `playwright test`:**

```bash
docker compose up -d
curl http://localhost:8001/api/health   # {"status":"ok"}
```

---

## Mocking parse (required)

Never hit live DeepSeek in E2E. Intercept at the network layer:

```ts
// e2e/helpers/api-mocks.ts
import { Page } from '@playwright/test';
import standardWeek from '../fixtures/responses/standard-week.json';

export async function mockParseSuccess(page: Page) {
  await page.route('**/api/stores/*/hours/parse', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(standardWeek),
    });
  });
}

export async function mockParseClarification(page: Page) {
  await page.route('**/api/stores/*/hours/parse', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        clarification_needed: true,
        question: 'Which days should be affected?',
      }),
    });
  });
}
```

**Apply flow options:**

| Approach | Pros | Cons |
|----------|------|------|
| Mock PATCH too | Fastest, no DB mutation | Doesn't prove persistence |
| Real PATCH + GET assert | Proves end-to-end write | Needs DB reset between tests |

**Recommended:** mock parse always; use **real PATCH** in one spec with DB reset.

---

## Page Object Model

One page object for the single screen — keep it light.

```ts
// e2e/pages/store-hours.page.ts
import { Page, expect } from '@playwright/test';

export class StoreHoursPage {
  constructor(private page: Page) {}

  textarea = () => this.page.getByTestId('hours-input');
  parseBtn = () => this.page.getByTestId('hours-parse-btn');
  applyBtn = () => this.page.getByTestId('hours-apply-btn');
  editBtn  = () => this.page.getByTestId('hours-edit-btn');
  preview  = () => this.page.getByTestId('hours-preview');
  success  = () => this.page.getByTestId('hours-success');
  error    = () => this.page.getByTestId('hours-error');

  async goto() {
    await this.page.goto('/');
  }

  async parsePrompt(text: string) {
    await this.textarea().fill(text);
    await this.parseBtn().click();
    await expect(this.preview()).toBeVisible();
  }
}
```

---

## Required test specs

| File | Scenario |
|------|----------|
| `parse-and-preview.spec.ts` | Mock parse → table shows correct days/times |
| `apply-hours.spec.ts` | Apply → `PATCH` request payload + success message |
| `clarification-flow.spec.ts` | Mock `clarification_needed` → warning, no Apply |
| `edit-without-apply.spec.ts` | Parse → Edit → no PATCH, preview hidden |
| `error-state.spec.ts` | Mock 500 on parse → error banner |

### Example: apply with network assertion

```ts
import { test, expect } from '@playwright/test';
import { StoreHoursPage } from '../pages/store-hours.page';
import { mockParseSuccess } from '../helpers/api-mocks';
import { STANDARD_WEEK_PROMPT } from '../fixtures/prompts';

test('apply sends PATCH and shows success', async ({ page }) => {
  await mockParseSuccess(page);
  const storeHours = new StoreHoursPage(page);
  await storeHours.goto();
  await storeHours.parsePrompt(STANDARD_WEEK_PROMPT);

  const patchPromise = page.waitForRequest(
    (req) => req.method() === 'PATCH' && req.url().includes('/hours'),
  );

  await storeHours.applyBtn().click();

  const patch = await patchPromise;
  const body = JSON.parse(patch.postData()!);
  expect(body.days).toHaveLength(7);
  await expect(storeHours.success()).toBeVisible();
});
```

---

## DB state management

Seeded store: `store_id = 1` (`Demo Burger`).

**Before suite (CI or local reset):**

```bash
docker compose exec app php artisan migrate:fresh --seed
```

**Optional helper:**

```ts
// e2e/helpers/db-reset.ts
import { API_URL } from '../playwright.config';

export async function resetDatabase() {
  // Call artisan via exec in globalSetup, or hit a test-only endpoint if added
}
```

Use `fullyParallel: false` and `workers: 1` until you have isolated stores per test.

---

## Vue component requirements

Add `data-testid` to `StoreHoursNaturalInput.vue` before writing specs:

| Element | `data-testid` |
|---------|---------------|
| Textarea | `hours-input` |
| Parse button | `hours-parse-btn` |
| Preview container | `hours-preview` |
| Apply button | `hours-apply-btn` |
| Edit button | `hours-edit-btn` |
| Success message | `hours-success` |
| Error message | `hours-error` |

See [testing-standards.md](./testing-standards.md) for naming rules.

---

## CI pipeline (sketch)

```yaml
# .github/workflows/e2e.yml
steps:
  - docker compose up -d --build
  - curl --retry 10 http://localhost:8001/api/health
  - docker compose exec app php artisan migrate:fresh --seed
  - cd e2e && npm ci && npx playwright install chromium
  - cd e2e && FRONTEND_URL=http://localhost:5173 npx playwright test
  - uses: actions/upload-artifact@v4
    if: failure()
    with:
      name: playwright-report
      path: e2e/playwright-report/
```

---

## Anti-patterns

| Avoid | Use instead |
|-------|-------------|
| `page.waitForTimeout(3000)` | `expect(locator).toBeVisible()` |
| Live DeepSeek in E2E | `page.route()` mocks |
| CSS class selectors (`.btn-apply`) | `data-testid` |
| 20+ Playwright specs | PHPUnit for API edge cases |
| Playwright inside `frontend/` | Root `e2e/` folder |
