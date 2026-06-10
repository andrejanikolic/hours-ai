import { test, expect } from '@playwright/test';
import { ServingTimesPage } from '../pages/serving-times.page';
import { mockParseClarification } from '../helpers/api-mocks';
import { CUTOFF_PROMPT } from '../fixtures/prompts';

// P0 — when the model needs more info, show the clarification banner and no Apply.
test('clarification response shows a banner and no preview/apply', async ({ page }) => {
  await mockParseClarification(page, 'Which days should the cutoff apply to?');
  const st = new ServingTimesPage(page);

  await st.gotoVenue();
  await st.typeAndParse(CUTOFF_PROMPT);

  await expect(st.clarification()).toBeVisible();
  await expect(st.clarification()).toContainText('Which days');
  await expect(st.preview()).toHaveCount(0);
  await expect(st.applyBtn()).toHaveCount(0);
});
