import { describe, expect, it } from 'vitest'
import { promptMentions } from './promptTargets'

describe('promptMentions', () => {
  it('matches a whole word case-insensitively', () => {
    expect(promptMentions('Downtown open 9 to 5', 'Downtown')).toBe(true)
    expect(promptMentions('downtown open 9 to 5', 'Downtown')).toBe(true)
  })

  it('matches a multi-word phrase', () => {
    expect(promptMentions('catering delivery 9 to 5', 'Catering Delivery')).toBe(true)
  })

  it('does not match a fragment of a larger word', () => {
    expect(promptMentions('downtown open late', 'Down')).toBe(false)
    expect(promptMentions('the eatery is open', 'eat')).toBe(false)
  })

  it('matches names sitting next to punctuation', () => {
    expect(promptMentions('Open "Airport", 8-10', 'Airport')).toBe(true)
    expect(promptMentions('Downtown: 9-5', 'Downtown')).toBe(true)
  })

  it('ignores empty or single-char names', () => {
    expect(promptMentions('anything', '')).toBe(false)
    expect(promptMentions('a place', 'a')).toBe(false)
  })

  it('is false when the name is absent', () => {
    expect(promptMentions('open every day 9 to 5', 'Westfield')).toBe(false)
  })

  it('handles regex-special characters in names literally', () => {
    expect(promptMentions("Joe's (Main) open 9-5", "Joe's (Main)")).toBe(true)
    expect(promptMentions('open 9-5', 'a.b')).toBe(false)
  })
})
