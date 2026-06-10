import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DayChips from './DayChips.vue'
import type { DayName } from '../../types'

const ALL: DayName[] = [
  'monday',
  'tuesday',
  'wednesday',
  'thursday',
  'friday',
  'saturday',
  'sunday',
]

function summaryOf(days: DayName[]): string {
  const wrapper = mount(DayChips, { props: { modelValue: days, interactive: false } })
  return wrapper.find('.day-summary').text()
}

describe('DayChips summary (read-only)', () => {
  it('shows an em dash for no days', () => {
    expect(summaryOf([])).toBe('—')
  })

  it('shows "Every day" for all seven', () => {
    expect(summaryOf(ALL)).toBe('Every day')
  })

  it('collapses a consecutive run into a range', () => {
    expect(summaryOf(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])).toBe('Mon–Fri')
  })

  it('renders a single day without a range', () => {
    expect(summaryOf(['wednesday'])).toBe('Wed')
  })

  it('joins separate groups, ordering by weekday regardless of input order', () => {
    expect(summaryOf(['saturday', 'monday', 'tuesday', 'thursday'])).toBe('Mon–Tue, Thu, Sat')
  })

  it('is robust to duplicate days', () => {
    expect(summaryOf(['monday', 'monday', 'tuesday'])).toBe('Mon–Tue')
  })
})

describe('DayChips interactive', () => {
  it('renders seven chips by default', () => {
    const wrapper = mount(DayChips, { props: { modelValue: [] } })
    expect(wrapper.findAll('.chip')).toHaveLength(7)
  })

  it('marks selected days with the active class', () => {
    const wrapper = mount(DayChips, { props: { modelValue: ['monday'] } })
    const monday = wrapper.findAll('.chip')[0]
    expect(monday.classes()).toContain('chip--on')
  })

  it('emits the day added when an unselected chip is clicked', async () => {
    const wrapper = mount(DayChips, { props: { modelValue: ['monday'] } })
    await wrapper.findAll('.chip')[1].trigger('click') // tuesday
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([['monday', 'tuesday']])
  })

  it('emits the day removed when a selected chip is clicked', async () => {
    const wrapper = mount(DayChips, { props: { modelValue: ['monday', 'tuesday'] } })
    await wrapper.findAll('.chip')[0].trigger('click') // monday off
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([['tuesday']])
  })
})
