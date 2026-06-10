import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, api } from './useApi'

const BASE = 'http://api.test'

/** Build a minimal Response-like object the `request` helper understands. */
function jsonResponse(status: number, body: unknown): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: { get: () => 'application/json' },
    json: async () => body,
    text: async () => JSON.stringify(body),
  } as unknown as Response
}

function noContent(): Response {
  return {
    status: 204,
    ok: true,
    headers: { get: () => null },
    json: async () => null,
    text: async () => '',
  } as unknown as Response
}

let fetchMock: ReturnType<typeof vi.fn>

beforeEach(() => {
  fetchMock = vi.fn()
  globalThis.fetch = fetchMock as unknown as typeof fetch
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('ApiError', () => {
  it('exposes status, message and field errors', () => {
    const err = new ApiError(422, 'Invalid', { name: ['required', 'too short'] })
    expect(err.name).toBe('ApiError')
    expect(err.status).toBe(422)
    expect(err.message).toBe('Invalid')
    expect(err.firstFieldError('name')).toBe('required')
  })

  it('firstFieldError returns undefined for unknown field', () => {
    const err = new ApiError(422, 'Invalid', { name: ['required'] })
    expect(err.firstFieldError('slug')).toBeUndefined()
  })

  it('firstFieldError is safe with no errors object', () => {
    expect(new ApiError(500, 'boom').firstFieldError('x')).toBeUndefined()
  })
})

describe('api request building', () => {
  it('GET prefixes base url and serializes query, skipping null/undefined', () => {
    fetchMock.mockResolvedValue(jsonResponse(200, []))
    api.get('/brands', { active: true, page: 2, q: null, missing: undefined })
    const url = fetchMock.mock.calls[0][0] as string
    expect(url.startsWith(`${BASE}/brands?`)).toBe(true)
    expect(url).toContain('active=true')
    expect(url).toContain('page=2')
    expect(url).not.toContain('q=')
    expect(url).not.toContain('missing')
  })

  it('normalizes a path that is missing the leading slash', () => {
    fetchMock.mockResolvedValue(jsonResponse(200, []))
    api.get('brands')
    expect(fetchMock.mock.calls[0][0]).toBe(`${BASE}/brands`)
  })

  it('POST sets JSON content-type and stringifies the body', async () => {
    fetchMock.mockResolvedValue(jsonResponse(201, { id: 1 }))
    const out = await api.post('/brands', { name: 'Acme' })
    const init = fetchMock.mock.calls[0][1] as RequestInit
    expect(init.method).toBe('POST')
    expect((init.headers as Record<string, string>)['Content-Type']).toBe('application/json')
    expect(init.body).toBe(JSON.stringify({ name: 'Acme' }))
    expect(out).toEqual({ id: 1 })
  })

  it('GET sends no body and no content-type header', () => {
    fetchMock.mockResolvedValue(jsonResponse(200, []))
    api.get('/brands')
    const init = fetchMock.mock.calls[0][1] as RequestInit
    expect(init.body).toBeUndefined()
    expect((init.headers as Record<string, string>)['Content-Type']).toBeUndefined()
  })

  it('returns undefined for 204 No Content', async () => {
    fetchMock.mockResolvedValue(noContent())
    await expect(api.del('/brands/1')).resolves.toBeUndefined()
  })
})

describe('api error handling', () => {
  it('throws ApiError carrying server message and field errors', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse(422, { message: 'Validation failed', errors: { name: ['required'] } }),
    )
    await expect(api.post('/brands', {})).rejects.toMatchObject({
      status: 422,
      message: 'Validation failed',
    })
  })

  it('falls back to a generic message when none is supplied', async () => {
    fetchMock.mockResolvedValue(jsonResponse(500, {}))
    await expect(api.get('/brands')).rejects.toThrow('Request failed (HTTP 500)')
  })
})
