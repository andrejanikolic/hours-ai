import { Page, Request } from '@playwright/test';
import standardWeek from '../fixtures/responses/serving-times/standard-week.json' assert { type: 'json' };

/**
 * Intercepts the DeepSeek-backed parse endpoint and returns a fixture.
 * The wire shape is `{ preview, should_update, clarification_needed, clarification_message }`
 * (see ParseResponseBody in frontend/src/types). Never let E2E hit live DeepSeek.
 */
export async function mockParseSuccess(page: Page, body: unknown = standardWeek) {
  await page.route('**/serving-times/parse', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(body),
    }),
  );
}

export async function mockParseClarification(
  page: Page,
  message = 'Which days should be affected?',
) {
  await page.route('**/serving-times/parse', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        preview: [],
        should_update: false,
        clarification_needed: true,
        clarification_message: message,
      }),
    }),
  );
}

export async function mockParseError(page: Page, status = 500) {
  await page.route('**/serving-times/parse', (route) =>
    route.fulfill({ status, contentType: 'application/json', body: '{}' }),
  );
}

/**
 * Mocks the write-back so specs stay deterministic and don't mutate the DB.
 * Apply calls `PUT /serving-times/replace`. Returns the captured request so the
 * caller can assert the payload.
 */
export async function mockReplaceSuccess(page: Page) {
  await page.route('**/serving-times/replace', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: '[]',
    }),
  );
}

/** Resolves with the PUT request to /serving-times/replace once it fires. */
export function waitForReplace(page: Page): Promise<Request> {
  return page.waitForRequest(
    (req) => req.method() === 'PUT' && req.url().includes('/serving-times/replace'),
  );
}

/**
 * Mocks the manual list (GET) and create (POST) on `/serving-times` so the
 * manual-add spec is deterministic and doesn't mutate the seeded DB (a real
 * POST could 422 on overlap with seeded slots). The regex matches
 * `/serving-times` and `/serving-times?<query>` but NOT `/serving-times/parse`,
 * `/serving-times/replace`, or `/serving-times/{id}`.
 */
export async function mockManualSlots(page: Page, initial: unknown[] = []) {
  await page.route(/\/serving-times(\?.*)?$/, async (route) => {
    const req = route.request();
    if (req.method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(initial),
      });
    }
    if (req.method() === 'POST') {
      const payload = JSON.parse(req.postData() ?? '{}');
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ id: 9001, ...payload }),
      });
    }
    return route.continue();
  });
}
