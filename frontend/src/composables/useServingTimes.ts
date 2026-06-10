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
     * Returns the flat parse result. Backend response shape:
     * `{ preview: ServingTimeInput[], clarification_needed, clarification_message }`.
     * `entityName` is optional context fed into the DeepSeek system prompt.
     */
    parse: async (
      parentType: ParentType,
      parentId: number,
      prompt: string,
      entityName?: string,
    ): Promise<ParseResult> => {
      const body = await api.post<ParseResponseBody>('/serving-times/parse', {
        parent_type: parentType,
        parent_id: parentId,
        prompt,
        ...(entityName ? { entity_name: entityName } : {}),
      })
      return {
        serving_times: Array.isArray(body.preview) ? body.preview : [],
        clarification_needed: body.clarification_needed ?? false,
        clarification_message: body.clarification_message ?? null,
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
