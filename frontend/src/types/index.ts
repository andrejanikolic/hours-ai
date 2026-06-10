export type DayName =
  | 'monday'
  | 'tuesday'
  | 'wednesday'
  | 'thursday'
  | 'friday'
  | 'saturday'
  | 'sunday'

export type ServingTimeType = 'weekday' | 'special'

export type ParentType = 'brand' | 'venue' | 'menu' | 'order_type'

export interface ServingTime {
  id: number
  parent_type: ParentType
  parent_id: number
  type: ServingTimeType
  days: DayName[] | null
  date: string | null
  date_to: string | null
  time_from: string | null
  time_to: string | null
  working: boolean
  created_at?: string
  updated_at?: string
}

/** Body shape for POST /serving-times and items inside parse/replace payloads. */
export interface ServingTimeInput {
  type: ServingTimeType
  days?: DayName[] | null
  date?: string | null
  date_to?: string | null
  time_from?: string | null
  time_to?: string | null
  working: boolean
}

export interface Brand {
  id: number
  name: string
  slug: string
  timezone: string | null
  active: boolean
  created_at?: string
  updated_at?: string
  serving_times?: ServingTime[]
  venues?: Venue[]
  menus?: Menu[]
}

export interface Venue {
  id: number
  brand_id: number
  name: string
  slug?: string | null
  address: string | null
  city?: string | null
  country?: string | null
  timezone: string | null
  phone?: string | null
  active: boolean
  created_at?: string
  updated_at?: string
  serving_times?: ServingTime[]
  order_types?: VenueOrderType[]
}

export interface Menu {
  id: number
  brand_id: number
  name: string
  internal_name?: string | null
  description: string | null
  active: boolean
  position?: number | null
  created_at?: string
  updated_at?: string
  serving_times?: ServingTime[]
}

/** Global order type (one of the seeded 4). */
export interface OrderType {
  id: number
  name: string
  slug: string
}

/**
 * Order type as attached to a venue.
 * `id` is the global order_type id.
 * `venue_order_type_id` is the pivot row id — this is the value used as
 * `parent_id` when `parent_type === 'order_type'` for serving times.
 */
export interface VenueOrderType {
  id: number
  name: string
  slug: string
  active: boolean
  venue_order_type_id: number
  serving_times: ServingTime[]
}

/** Flat, component-facing parse result (composable unwraps the `preview` envelope). */
export interface ParseResult {
  serving_times: ServingTimeInput[]
  clarification_needed: boolean
  clarification_message: string | null
}

/** Raw shape returned by POST /serving-times/parse. */
export interface ParseResponseBody {
  preview: ServingTimeInput[]
  clarification_needed: boolean
  clarification_message: string | null
}
