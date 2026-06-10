# Playwright Architecture — HoursAI

How to structure, configure, and run E2E tests for this project.

Aligned to the **serving-times** architecture (brands / venues / menus / order types). The legacy `/stores/{id}/hours` scaffold is P2 and not the focus of E2E.

---

## Location

Playwright lives at **repo root**, not inside `frontend/`:

```
hours-ai/
├── backend/
├── frontend/
└── e2e/
    ├── playwright.config.ts
    ├── package.json              ← e2e-only deps
    ├── fixtures/
    │   ├── prompts.ts
    │   └── responses/serving-times/
    │       ├── standard-week.json
    │       ├── christmas-closure.json
    │       ├── clarification.json
    │       └── server-error.json
    ├── pages/
    │   └── serving-times.page.ts
    ├── helpers/
    │   ├── api-mocks.ts
    │   └── db-reset.ts
    └── tests/
        ├── brand-serving-times-ai.spec.ts
        ├── brand-serving-times-clarification.spec.ts
        ├── edit-without-apply.spec.ts
        ├── manual-serving-time.spec.ts
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
const API_URL = process.env.API_URL ?? 'http://localhost:8080/api';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,           // shared seeded DB — avoid races
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

**Port note:** `docker-compose.yml` publishes nginx (the backend) on **8080**. Set `VITE_API_BASE_URL=http://localhost:8080/api` in `frontend/.env.local` before E2E. (Note: the committed `.env.local` currently reads `8001` — a known stale value flagged in `plan.md`; the specs need it pointing at the running backend on 8080.)

**Prerequisites before `playwright test`:**

```bash
docker compose up -d
curl http://localhost:8080/api/health   # {"status":"ok"}
```

---

## Mocking parse (required)

Never hit live DeepSeek in E2E. Intercept the parse endpoint at the network layer. The real endpoint is **`POST /api/serving-times/parse`** and it returns a `preview` array of serving-time rows:

```ts
// e2e/helpers/api-mocks.ts
import { Page } from '@playwright/test';
import standardWeek from '../fixtures/responses/serving-times/standard-week.json';

export async function mockParseSuccess(page: Page) {
  await page.route('**/api/serving-times/parse', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(standardWeek),
    });
  });
}

export async function mockParseClarification(page: Page) {
  await page.route('**/api/serving-times/parse', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        preview: [],
        should_update: false,
        clarification_needed: true,
        clarification_message: 'Which days should be affected?',
      }),
    });
  });
}

export async function mockParseError(page: Page) {
  await page.route('**/api/serving-times/parse', (route) =>
    route.fulfill({ status: 500, contentType: 'application/json', body: '{}' }),
  );
}
```

**Apply (write-back) flow options:**

The Apply action calls **`PUT /api/serving-times/replace`** (not PATCH).

| Approach | Pros | Cons |
|----------|------|------|
| Mock `PUT /serving-times/replace` too | Fastest, no DB mutation | Doesn't prove persistence |
| Real replace + GET assert | Proves end-to-end write | Needs DB reset between tests |

**Recommended:** mock parse always; use **real `replace`** in one spec with DB reset.

---

## Parse response schema

The mock fixtures must match what `ServingTimesController::parse` returns:

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

Row fields: `type` (`weekday` | `special`), `days` (lowercase full English, weekday rows only), `date` / `date_to` (`Y-m-d`, special rows), `time_from` / `time_to` (`HH:MM`), `working` (bool). See [testing-standards.md](./testing-standards.md) for the full fixture contract.

---

## Navigation flow (important)

`HoursAIPrompt` does **not** live on a single screen or a "Serving Times tab." The serving-times AI prompt renders inside the **detail views one level below the brand**:

```
/  (WelcomePage — pick a brand from the sidebar)
└── /brands/:brandId  (BrandDetailPage — tabs)
    ├── "Venue Opening Hours"        → VenuesTab → /brands/:b/venues/:v   (VenueDetailPage → HoursAIPrompt)
    ├── "Menu Serving Times"         → MenusTab  → /brands/:b/menus/:m    (MenuDetailPage → HoursAIPrompt)
    └── "Order Types Delivery Times" → OrderTypesTab (OrderTypeCard → HoursAIPrompt)
```

So a P0 journey is: open `/` → click a brand → open a tab → drill into a venue/menu/order-type → type prompt → Parse → preview → Apply. Seeded brands: **Demo Burger** (id 1, has seeded serving times), **Pasta House** (id 2), **Starbird** (id 3, many venues).

---

## Page Object Model

The page object wraps the `HoursAIPrompt` widget (shared by venue/menu/order-type views) plus navigation helpers.

```ts
// e2e/pages/serving-times.page.ts
import { Page, expect } from '@playwright/test';

export class ServingTimesPage {
  constructor(private page: Page) {}

  prompt   = () => this.page.getByTestId('hours-prompt-input');
  parseBtn = () => this.page.getByTestId('hours-parse-btn');
  applyBtn = () => this.page.getByTestId('hours-apply-btn');
  editBtn  = () => this.page.getByTestId('hours-edit-btn');
  preview  = () => this.page.getByTestId('hours-preview');
  success  = () => this.page.getByTestId('hours-success');       // toast
  error    = () => this.page.getByTestId('hours-error');
  clarify  = () => this.page.getByTestId('hours-clarification');

  /** Navigate straight to a venue detail view that renders HoursAIPrompt. */
  async gotoVenue(brandId: number, venueId: number) {
    await this.page.goto(`/brands/${brandId}/venues/${venueId}`);
  }

  async parsePrompt(text: string) {
    await this.prompt().fill(text);
    await this.parseBtn().click();
    await expect(this.preview()).toBeVisible();
  }
}
```

---

## Required test specs

| File | Scenario |
|------|----------|
| `brand-serving-times-ai.spec.ts` | Mock parse → preview rows show correct days/times; Apply → `PUT /serving-times/replace` payload + success toast |
| `brand-serving-times-clarification.spec.ts` | Mock `clarification_needed` → clarification banner, no Apply |
| `edit-without-apply.spec.ts` | Parse → Edit → no replace call, preview dismissed |
| `manual-serving-time.spec.ts` | Add a weekday slot via `AddServingTimeForm` → appears in manual list |
| `error-state.spec.ts` | Mock 500 on parse → error banner |

### Example: apply with network assertion

```ts
import { test, expect } from '@playwright/test';
import { ServingTimesPage } from '../pages/serving-times.page';
import { mockParseSuccess } from '../helpers/api-mocks';
import { STANDARD_WEEK_PROMPT } from '../fixtures/prompts';

test('apply sends PUT /serving-times/replace and shows success', async ({ page }) => {
  await mockParseSuccess(page);
  const st = new ServingTimesPage(page);
  await st.gotoVenue(1, 1);
  await st.parsePrompt(STANDARD_WEEK_PROMPT);

  const replacePromise = page.waitForRequest(
    (req) => req.method() === 'PUT' && req.url().includes('/serving-times/replace'),
  );

  await st.applyBtn().click();

  const replace = await replacePromise;
  const body = JSON.parse(replace.postData()!);
  expect(body.serving_times.length).toBeGreaterThan(0);
  await expect(st.success()).toBeVisible();
});
```

---

## DB state management

Seeded data (from `DatabaseSeeder`): brands **Demo Burger** (id 1), **Pasta House** (id 2), **Starbird** (id 3); order types Pickup / Delivery / Dine In / Drive Thru / Catering Delivery. The Demo Burger **brand** parent has a seeded weekday serving-time row.

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

Use `fullyParallel: false` and `workers: 1` until tests are isolated per parent (brand/venue/menu).

---

## Vue component requirements

Add `data-testid` to `HoursAIPrompt.vue` (and the manual list / nav hooks) before writing specs. `AppButton`'s root element is the `<button>`, so a `data-testid` placed on `<AppButton data-testid="…">` falls through to the button.

| Element | `data-testid` |
|---------|---------------|
| Prompt textarea | `hours-prompt-input` |
| Parse button | `hours-parse-btn` |
| Preview container | `hours-preview` |
| Apply button | `hours-apply-btn` |
| Edit prompt button | `hours-edit-btn` |
| Clarification banner | `hours-clarification` |
| Parse/apply error banner | `hours-error` |
| Success toast | `hours-success` |
| Manual serving-times list (`ServingTimesPanel`) | `serving-times-list` |

See [testing-standards.md](./testing-standards.md) for naming rules.

---

## CI pipeline (sketch)

```yaml
# .github/workflows/e2e.yml
steps:
  - docker compose up -d --build
  - curl --retry 10 http://localhost:8080/api/health
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
| Live DeepSeek in E2E | `page.route()` mocks on `/serving-times/parse` |
| CSS class selectors (`.btn-apply`) | `data-testid` |
| 20+ Playwright specs | PHPUnit for API edge cases (already built) |
| Playwright inside `frontend/` | Root `e2e/` folder |
| Mocking `PATCH /hours` (legacy) | Mock/assert `PUT /serving-times/replace` |
