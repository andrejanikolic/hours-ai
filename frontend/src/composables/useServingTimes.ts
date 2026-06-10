import { api } from './useApi'
import type {
  ParentType,
  ParseResponseBody,
  ParseResult,
  ServingTime,
  ServingTimeInput,
} from '../types'

/**
 * All serving-times routes are flat (`/serving-times*`). The parent is passed via
 * `parent_type` + `parent_id` in body (writes) or query string (reads). For
 * `parent_type === 'order_type'`, `parent_id` is the venue_order_type pivot id,
 * NOT the global order_type id.
 */
export function useServingTimes() {
  return {
    list: (parentType: ParentType, parentId: number) =>
      api.get<ServingTime[]>('/serving-times', {
        parent_type: parentType,
        parent_id: parentId,
      }),

    create: (parentType: ParentType, parentId: number, slot: ServingTimeInput) =>
      api.post<ServingTime>('/serving-times', {
        parent_type: parentType,
        parent_id: parentId,
        ...slot,
      }),

    remove: (servingTimeId: number) => api.del(`/serving-times/${servingTimeId}`),

    /**
     * Returns the flat parse result. The backend wraps under `preview` — we
     * unwrap here so components don't have to know about the envelope.
     */
    parse: async (
      parentType: ParentType,
      parentId: number,
      prompt: string,
    ): Promise<ParseResult> => {
      const body = await api.post<ParseResponseBody>('/serving-times/parse', {
        parent_type: parentType,
        parent_id: parentId,
        prompt,
      })
      return {
        serving_times: body.preview?.serving_times ?? [],
        clarification_needed:
          body.clarification_needed ?? body.preview?.clarification_needed ?? false,
      }
    },

    replace: (parentType: ParentType, parentId: number, items: ServingTimeInput[]) =>
      api.put<ServingTime[]>('/serving-times/replace', {
        parent_type: parentType,
        parent_id: parentId,
        serving_times: items,
      }),
  }
}
