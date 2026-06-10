// Demo prompts — keep identical to docs/test-plan.md so PHPUnit and Playwright
// exercise the same strings.

export const STANDARD_WEEK_PROMPT =
  "We're open Mon-Fri 8am to 10pm, Saturday 9am to 11pm, and closed Sundays.";

export const CHRISTMAS_CLOSURE_PROMPT =
  "Close all online ordering on Christmas Day and New Year's Day.";

export const WEEKEND_BUFFER_PROMPT =
  'Add a 15-minute pickup buffer on weekends only.';

export const CUTOFF_PROMPT =
  'We stop taking orders 30 minutes before closing every day.';

export const SPLIT_WINDOW_PROMPT =
  'Enable delivery only between 11am and 9pm, keep pickup available until close.';
