import { api } from './useApi'
import type { OrderType, VenueOrderType } from '../types'

/**
 * Hardcoded global order types — the backend seeds exactly these 4.
 * Order matters: rendered left-to-right in the UI.
 */
export const GLOBAL_ORDER_TYPES: readonly OrderType[] = [
  { id: 1, name: 'Pickup', slug: 'pickup' },
  { id: 2, name: 'Delivery', slug: 'delivery' },
  { id: 3, name: 'Dine In', slug: 'dine-in' },
  { id: 4, name: 'Drive Thru', slug: 'drive-thru' },
] as const

export function useVenueOrderTypes() {
  return {
    list: (brandId: number, venueId: number) =>
      api.get<VenueOrderType[]>(`/brands/${brandId}/venues/${venueId}/order-types`),

    /** Returns the pivot id. Caller should re-fetch the list to get the full record. */
    attach: (brandId: number, venueId: number, orderTypeId: number) =>
      api.post<{ venue_order_type_id: number }>(
        `/brands/${brandId}/venues/${venueId}/order-types`,
        { order_type_id: orderTypeId },
      ),

    /** URL uses the global order_type id, NOT the pivot id. */
    detach: (brandId: number, venueId: number, orderTypeId: number) =>
      api.del(`/brands/${brandId}/venues/${venueId}/order-types/${orderTypeId}`),
  }
}
