import { test, expect } from '@playwright/test';
import { ServingTimesPage } from '../pages/serving-times.page';
import { mockManualSlots } from '../helpers/api-mocks';

// P1 — manual path: add a weekday slot via AddServingTimeForm → appears in list.
// List + create are mocked so the spec is deterministic and never mutates the
// seeded DB. The venue page itself still loads from the real backend.
test('adds a weekday slot manually and shows it in the list', async ({ page }) => {
  await mockManualSlots(page, []);
  const st = new ServingTimesPage(page);

  await st.gotoVenue(); // venue Serving Times → HoursAI subtab (default)

  // Switch to the Manual subtab.
  await page.getByRole('tab', { name: 'Manual' }).click();

  // Open the inline create form.
  await page.getByRole('button', { name: '+ Add slot' }).click();

  // Weekday is the default type; pick Monday. Times default to 09:00–17:00.
  await page.getByRole('button', { name: 'Mon', exact: true }).click();

  // Save (the form's submit button is labelled "Add slot", exact to avoid the
  // now-hidden "+ Add slot" trigger).
  await page.getByRole('button', { name: 'Add slot', exact: true }).click();

  // Success toast + the new row (read-only day summary "Mon", 09:00 – 17:00).
  await expect(page.getByText('Slot added')).toBeVisible();
  await expect(page.getByText('09:00 – 17:00')).toBeVisible();
});
