/**
 * Helpers for inferring which entities a free-text prompt is about, so the user
 * can name a venue/menu/channel directly ("Downtown open 9–5") instead of
 * ticking it in the table first.
 */

/** Escape a literal string for use inside a RegExp. */
function escapeRegExp(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * True if `name` appears in `prompt` as a whole word/phrase (case-insensitive),
 * not as a fragment of a larger word — so "Downtown" matches "downtown" but
 * "Down" does not match "downtown", and "Catering Delivery" matches the full
 * two-word phrase.
 */
export function promptMentions(prompt: string, name: string | null | undefined): boolean {
  const n = (name ?? '').trim()
  if (n.length < 2) return false
  const re = new RegExp(`(^|[^a-z0-9])${escapeRegExp(n.toLowerCase())}([^a-z0-9]|$)`, 'i')
  return re.test(prompt.toLowerCase())
}
