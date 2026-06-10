import { test, expect } from '@playwright/test';
import { ServingTimesPage } from '../pages/serving-times.page';
import { mockParseError } from '../helpers/api-mocks';
import { STANDARD_WEEK_PROMPT } from '../fixtures/prompts';

// P2 — a 500 from parse surfaces a friendly error banner, no preview.
test('parse failure shows an error banner', async ({ page }) => {
  await mockParseError(page, 500);
  const st = new ServingTimesPage(page);

  await st.gotoVenue();
  await st.typeAndParse(STANDARD_WEEK_PROMPT);

  await expect(st.error()).toBeVisible();
  await expect(st.preview()).toHaveCount(0);
});
