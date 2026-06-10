import { describe, expect, it } from 'vitest'
import { sameSchedule, slotKey } from './scheduleCompare'
import type { ServingTime, ServingTimeInput } from '../types'

function weekday(over: Partial<ServingTimeInput> = {}): ServingTimeInput {
  return {
    type: 'weekday',
    days: ['monday', 'tuesday'],
    time_from: '09:00',
    time_to: '17:00',
    working: true,
    ...over,
  }
}

function special(over: Partial<ServingTimeInput> = {}): ServingTimeInput {
  return {
    type: 'special',
    date: '2026-12-25',
    date_to: null,
    time_from: null,
    time_to: null,
    working: false,
    ...over,
  }
}

describe('slotKey', () => {
  it('is independent of day order', () => {
    const a = weekday({ days: ['monday', 'tuesday', 'wednesday'] })
    const b = weekday({ days: ['wednesday', 'monday', 'tuesday'] })
    expect(slotKey(a)).toBe(slotKey(b))
  })

  it('differs when times differ', () => {
    expect(slotKey(weekday({ time_to: '17:00' }))).not.toBe(
      slotKey(weekday({ time_to: '18:00' })),
    )
  })

  it('differs when working flag differs', () => {
    expect(slotKey(weekday({ working: true }))).not.toBe(
      slotKey(weekday({ working: false })),
    )
  })

  it('treats missing days as empty', () => {
    expect(slotKey(weekday({ days: undefined }))).toBe(slotKey(weekday({ days: [] })))
  })

  it('weekday and special never collide', () => {
    expect(slotKey(weekday())).not.toBe(slotKey(special()))
  })

  it('keys special by date range and times', () => {
    expect(slotKey(special({ date: '2026-12-25' }))).not.toBe(
      slotKey(special({ date: '2026-12-26' })),
    )
  })

  it('ignores id — content-only identity', () => {
    const withId = { ...weekday(), id: 42 } as unknown as ServingTime
    expect(slotKey(withId)).toBe(slotKey(weekday()))
  })
})

describe('sameSchedule', () => {
  it('returns true for reordered identical lists', () => {
    const a = [weekday(), special()]
    const b = [special(), weekday()]
    expect(sameSchedule(a, b)).toBe(true)
  })

  it('collapses duplicates', () => {
    expect(sameSchedule([weekday(), weekday()], [weekday()])).toBe(true)
  })

  it('returns false when one list has an extra unique slot', () => {
    expect(sameSchedule([weekday()], [weekday(), special()])).toBe(false)
  })

  it('returns false on content difference', () => {
    expect(sameSchedule([weekday({ time_to: '17:00' })], [weekday({ time_to: '22:00' })])).toBe(
      false,
    )
  })

  it('two empty lists are equal', () => {
    expect(sameSchedule([], [])).toBe(true)
  })
})
