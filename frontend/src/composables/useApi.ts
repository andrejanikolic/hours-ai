const BASE_URL = import.meta.env.VITE_API_BASE_URL as string

export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** First error message for a given field (for inline form display). */
  firstFieldError(field: string): string | undefined {
    return this.errors?.[field]?.[0]
  }
}

type Primitive = string | number | boolean
type Query = Record<string, Primitive | null | undefined>

function buildUrl(path: string, query?: Query): string {
  const normalized = path.startsWith('/') ? path : `/${path}`
  const url = `${BASE_URL}${normalized}`
  if (!query) return url

  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value === null || value === undefined) continue
    params.append(key, String(value))
  }
  const qs = params.toString()
  return qs ? `${url}?${qs}` : url
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  query?: Query,
): Promise<T> {
  const hasBody = body !== undefined
  const res = await fetch(buildUrl(path, query), {
    method,
    headers: {
      Accept: 'application/json',
      ...(hasBody ? { 'Content-Type': 'application/json' } : {}),
    },
    body: hasBody ? JSON.stringify(body) : undefined,
  })

  if (res.status === 204) return undefined as T

  let data: unknown = null
  const ct = res.headers.get('content-type') ?? ''
  if (ct.includes('application/json')) {
    try {
      data = await res.json()
    } catch {
      data = null
    }
  } else {
    try {
      data = await res.text()
    } catch {
      data = null
    }
  }

  if (!res.ok) {
    const payload = (data ?? {}) as Partial<{ message: string; errors: Record<string, string[]> }>
    throw new ApiError(
      res.status,
      payload.message ?? `Request failed (HTTP ${res.status})`,
      payload.errors,
    )
  }

  return data as T
}

export const api = {
  get:   <T>(path: string, query?: Query)  => request<T>('GET', path, undefined, query),
  post:  <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  put:   <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  del:   (path: string)                    => request<void>('DELETE', path),
}
