import { api } from './useApi'
import type { Venue } from '../types'

export interface VenueCreateBody {
  name: string
  slug?: string
  address?: string
  city?: string
  country?: string
  timezone?: string
  phone?: string
  active?: boolean
}

export interface VenueUpdateBody {
  name?: string
  slug?: string
  address?: string | null
  city?: string | null
  country?: string
  timezone?: string | null
  phone?: string | null
  active?: boolean
}

export function useVenues() {
  return {
    list: (brandId: number) => api.get<Venue[]>(`/brands/${brandId}/venues`),
    get: (brandId: number, venueId: number) =>
      api.get<Venue>(`/brands/${brandId}/venues/${venueId}`),
    create: (brandId: number, body: VenueCreateBody) =>
      api.post<Venue>(`/brands/${brandId}/venues`, body),
    update: (brandId: number, venueId: number, body: VenueUpdateBody) =>
      api.patch<Venue>(`/brands/${brandId}/venues/${venueId}`, body),
    remove: (brandId: number, venueId: number) =>
      api.del(`/brands/${brandId}/venues/${venueId}`),
  }
}
