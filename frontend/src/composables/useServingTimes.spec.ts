import { beforeEach, describe, expect, it, vi } from 'vitest'

// Mock the api layer so we assert path/body wiring without real HTTP.
vi.mock('./useApi', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    del: vi.fn(),
  },
}))

import { api } from './useApi'
import { useServingTimes } from './useServingTimes'
import type { ServingTimeInput } from '../types'

const get = api.get as ReturnType<typeof vi.fn>
const post = api.post as ReturnType<typeof vi.fn>
const put = api.put as ReturnType<typeof vi.fn>
const del = api.del as ReturnType<typeof vi.fn>

const slot: ServingTimeInput = {
  type: 'weekday',
  days: ['monday'],
  time_from: '09:00',
  time_to: '17:00',
  working: true,
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useServingTimes', () => {
  it('list passes parent_type and parent_id as query', () => {
    get.mockResolvedValue([])
    useServingTimes().list('venue', 7)
    expect(get).toHaveBeenCalledWith('/serving-times', { parent_type: 'venue', parent_id: 7 })
  })

  it('create merges parent fields with the slot body', () => {
    post.mockResolvedValue({})
    useServingTimes().create('menu', 3, slot)
    expect(post).toHaveBeenCalledWith('/serving-times', {
      parent_type: 'menu',
      parent_id: 3,
      ...slot,
    })
  })

  it('remove targets the serving-time id', () => {
    del.mockResolvedValue(undefined)
    useServingTimes().remove(55)
    expect(del).toHaveBeenCalledWith('/serving-times/55')
  })

  it('replace sends the full items array', () => {
    put.mockResolvedValue([])
    useServingTimes().replace('order_type', 12, [slot])
    expect(put).toHaveBeenCalledWith('/serving-times/replace', {
      parent_type: 'order_type',
      parent_id: 12,
      serving_times: [slot],
    })
  })

  describe('parse', () => {
    it('unwraps the preview envelope into the flat result', async () => {
      post.mockResolvedValue({
        preview: [slot],
        clarification_needed: false,
        clarification_message: null,
      })
      const res = await useServingTimes().parse('brand', 1, 'open weekdays 9-5')
      expect(res).toEqual({
        serving_times: [slot],
        clarification_needed: false,
        clarification_message: null,
      })
    })

    it('omits entity_name when not provided', async () => {
      post.mockResolvedValue({ preview: [] })
      await useServingTimes().parse('brand', 1, 'prompt')
      expect(post).toHaveBeenCalledWith('/serving-times/parse', {
        parent_type: 'brand',
        parent_id: 1,
        prompt: 'prompt',
      })
    })

    it('includes entity_name when provided', async () => {
      post.mockResolvedValue({ preview: [] })
      await useServingTimes().parse('venue', 2, 'prompt', 'Downtown')
      expect(post).toHaveBeenCalledWith('/serving-times/parse', {
        parent_type: 'venue',
        parent_id: 2,
        prompt: 'prompt',
        entity_name: 'Downtown',
      })
    })

    it('defaults to safe values when the envelope is incomplete', async () => {
      post.mockResolvedValue({ preview: undefined })
      const res = await useServingTimes().parse('brand', 1, 'x')
      expect(res).toEqual({
        serving_times: [],
        clarification_needed: false,
        clarification_message: null,
      })
    })

    it('surfaces clarification requests from the backend', async () => {
      post.mockResolvedValue({
        preview: [],
        clarification_needed: true,
        clarification_message: 'Which days?',
      })
      const res = await useServingTimes().parse('brand', 1, 'sometimes')
      expect(res.clarification_needed).toBe(true)
      expect(res.clarification_message).toBe('Which days?')
    })
  })
})
