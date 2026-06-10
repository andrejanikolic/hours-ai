import { test, expect } from '@playwright/test';
import { ServingTimesPage } from '../pages/serving-times.page';
import { mockParseSuccess } from '../helpers/api-mocks';
import { STANDARD_WEEK_PROMPT } from '../fixtures/prompts';

// P0 — Edit returns to the prompt and never writes back.
test('Edit dismisses the preview without calling replace', async ({ page }) => {
  await mockParseSuccess(page);
  const st = new ServingTimesPage(page);

  let replaceCalled = false;
  page.on('request', (req) => {
    if (req.method() === 'PUT' && req.url().includes('/serving-times/replace')) {
      replaceCalled = true;
    }
  });

  await st.gotoVenue();
  await st.typeAndParse(STANDARD_WEEK_PROMPT);
  await expect(st.preview()).toBeVisible();

  await st.editBtn().click();

  await expect(st.preview()).toHaveCount(0);
  await expect(st.prompt()).toBeVisible();
  expect(replaceCalled).toBe(false);
});
