import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useToast } from './useToast'

beforeEach(() => {
  vi.useFakeTimers()
  // Drain any toasts left from a previous test.
  const { toasts, dismiss } = useToast()
  for (const t of [...toasts.value]) dismiss(t.id)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useToast', () => {
  it('pushes a success toast with kind and message', () => {
    const { success, toasts } = useToast()
    success('Saved')
    expect(toasts.value).toHaveLength(1)
    expect(toasts.value[0]).toMatchObject({ kind: 'success', message: 'Saved' })
  })

  it('assigns increasing unique ids', () => {
    const { success } = useToast()
    const id1 = success('a')
    const id2 = success('b')
    expect(id2).toBeGreaterThan(id1)
  })

  it('auto-dismisses after the duration', () => {
    const { info, toasts } = useToast()
    info('hi', 1000)
    expect(toasts.value).toHaveLength(1)
    vi.advanceTimersByTime(1000)
    expect(toasts.value).toHaveLength(0)
  })

  it('does not auto-dismiss when duration is 0', () => {
    const { error, toasts } = useToast()
    error('stays', 0)
    vi.advanceTimersByTime(10_000)
    expect(toasts.value).toHaveLength(1)
  })

  it('dismiss removes only the targeted toast', () => {
    const { success, dismiss, toasts } = useToast()
    const a = success('a', 0)
    success('b', 0)
    dismiss(a)
    expect(toasts.value.map((t) => t.message)).toEqual(['b'])
  })

  it('dismiss with an unknown id is a no-op', () => {
    const { success, dismiss, toasts } = useToast()
    success('a', 0)
    dismiss(99_999)
    expect(toasts.value).toHaveLength(1)
  })
})
