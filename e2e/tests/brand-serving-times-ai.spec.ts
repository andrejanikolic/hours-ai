import { test, expect } from '@playwright/test';
import { ServingTimesPage } from '../pages/serving-times.page';
import {
  mockParseSuccess,
  mockReplaceSuccess,
  waitForReplace,
} from '../helpers/api-mocks';
import { STANDARD_WEEK_PROMPT } from '../fixtures/prompts';

// P0 — core operator journey: type → Parse → preview → Apply → success.
test.describe('HoursAI serving-times — happy path', () => {
  test('parses a prompt and shows the preview', async ({ page }) => {
    await mockParseSuccess(page);
    const st = new ServingTimesPage(page);

    await st.gotoVenue();
    await st.typeAndParse(STANDARD_WEEK_PROMPT);

    await expect(st.preview()).toBeVisible();
  });

  test('Apply sends PUT /serving-times/replace and shows the success toast', async ({
    page,
  }) => {
    await mockParseSuccess(page);
    await mockReplaceSuccess(page);
    const st = new ServingTimesPage(page);

    await st.gotoVenue();
    await st.typeAndParse(STANDARD_WEEK_PROMPT);
    await expect(st.preview()).toBeVisible();

    const replacePromise = waitForReplace(page);
    await st.applyAndConfirm();

    const replace = await replacePromise;
    const body = JSON.parse(replace.postData() ?? '{}');
    expect(body.parent_type).toBe('venue');
    expect(Array.isArray(body.serving_times)).toBe(true);
    expect(body.serving_times.length).toBe(3); // Mon–Fri, Sat, Sun(closed)

    await expect(st.successToast()).toBeVisible();
  });
});
