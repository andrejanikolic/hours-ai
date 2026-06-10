import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('./useApi', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    del: vi.fn(),
  },
}))

import { api } from './useApi'
import { useBrands } from './useBrands'

const get = api.get as ReturnType<typeof vi.fn>
const post = api.post as ReturnType<typeof vi.fn>
const patch = api.patch as ReturnType<typeof vi.fn>
const del = api.del as ReturnType<typeof vi.fn>

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useBrands', () => {
  it('list hits the collection endpoint', () => {
    get.mockResolvedValue([])
    useBrands().list()
    expect(get).toHaveBeenCalledWith('/brands')
  })

  it('get builds the member url from id', () => {
    get.mockResolvedValue({})
    useBrands().get(9)
    expect(get).toHaveBeenCalledWith('/brands/9')
  })

  it('create posts the body unchanged', () => {
    post.mockResolvedValue({})
    const body = { name: 'Acme', timezone: 'UTC' }
    useBrands().create(body)
    expect(post).toHaveBeenCalledWith('/brands', body)
  })

  it('update patches the member url with the partial body', () => {
    patch.mockResolvedValue({})
    useBrands().update(4, { active: false })
    expect(patch).toHaveBeenCalledWith('/brands/4', { active: false })
  })

  it('remove deletes the member url', () => {
    del.mockResolvedValue(undefined)
    useBrands().remove(4)
    expect(del).toHaveBeenCalledWith('/brands/4')
  })
})
