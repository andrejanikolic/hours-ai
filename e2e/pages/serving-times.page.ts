import { Page, Locator, expect } from '@playwright/test';

/**
 * Wraps the HoursAI serving-times widget, which renders inside the venue/menu/
 * order-type detail views (not on a standalone screen). The default seeded
 * target is Demo Burger (brand 1) → Downtown venue (venue 1).
 *
 * Navigation: /brands/:b/venues/:v → "Serving Times" tab → "HoursAI" subtab
 * (the HoursAI subtab is selected by default).
 */
export class ServingTimesPage {
  constructor(private page: Page) {}

  // HoursAIPrompt hooks (see frontend testids)
  prompt = (): Locator => this.page.getByTestId('hours-prompt-input');
  parseBtn = (): Locator => this.page.getByTestId('hours-parse-btn');
  applyBtn = (): Locator => this.page.getByTestId('hours-apply-btn');
  editBtn = (): Locator => this.page.getByTestId('hours-edit-btn');
  preview = (): Locator => this.page.getByTestId('hours-preview');
  error = (): Locator => this.page.getByTestId('hours-error');
  clarification = (): Locator => this.page.getByTestId('hours-clarification');

  // Apply opens a "Replace all" confirmation (ConfirmDelete) before the PUT fires.
  confirmReplaceBtn = (): Locator =>
    this.page.getByRole('button', { name: 'Replace all' });

  // Success is shown as a toast, not an inline banner.
  successToast = (): Locator => this.page.getByText('Serving times updated');

  /** Open the venue's Serving Times → HoursAI prompt. */
  async gotoVenue(brandId = 1, venueId = 1): Promise<void> {
    await this.page.goto(`/brands/${brandId}/venues/${venueId}`);
    await this.page.getByRole('tab', { name: 'Serving Times' }).click();
    await expect(this.prompt()).toBeVisible();
  }

  async typeAndParse(text: string): Promise<void> {
    await this.prompt().fill(text);
    await this.parseBtn().click();
  }

  /** Click Apply, confirm "Replace all". */
  async applyAndConfirm(): Promise<void> {
    await this.applyBtn().click();
    await this.confirmReplaceBtn().click();
  }
}
