import type { ServingTime, ServingTimeInput } from '../types'

type AnySlot = ServingTime | ServingTimeInput

/**
 * Identity key for a schedule slot — content-only, no id. Two slots with the
 * same key represent the exact same schedule entry (same days, same times,
 * same working flag).
 */
export function slotKey(s: AnySlot): string {
  if (s.type === 'weekday') {
    const days = [...(s.days ?? [])].sort().join(',')
    return `weekday|${days}|${s.time_from ?? ''}|${s.time_to ?? ''}|${s.working}`
  }
  return `special|${s.date ?? ''}|${s.date_to ?? ''}|${s.time_from ?? ''}|${s.time_to ?? ''}|${s.working}`
}

/**
 * Returns true if two slot lists represent the same schedule (set equality on
 * content-keys, order irrelevant, duplicates collapsed).
 */
export function sameSchedule(a: AnySlot[], b: AnySlot[]): boolean {
  const ka = new Set(a.map(slotKey))
  const kb = new Set(b.map(slotKey))
  if (ka.size !== kb.size) return false
  for (const k of ka) if (!kb.has(k)) return false
  return true
}
