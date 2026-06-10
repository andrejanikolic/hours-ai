import { api } from './useApi'
import type { Brand } from '../types'

export interface BrandCreateBody {
  name: string
  slug?: string
  timezone?: string
  active?: boolean
}

export interface BrandUpdateBody {
  name?: string
  slug?: string
  timezone?: string
  active?: boolean
}

export function useBrands() {
  return {
    list: () => api.get<Brand[]>('/brands'),
    get: (id: number) => api.get<Brand>(`/brands/${id}`),
    create: (body: BrandCreateBody) => api.post<Brand>('/brands', body),
    update: (id: number, body: BrandUpdateBody) => api.patch<Brand>(`/brands/${id}`, body),
    remove: (id: number) => api.del(`/brands/${id}`),
  }
}
