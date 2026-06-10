import { api } from './useApi'
import type { Menu } from '../types'

export interface MenuCreateBody {
  name: string
  internal_name?: string
  description?: string
  active?: boolean
  position?: number
}

export interface MenuUpdateBody {
  name?: string
  internal_name?: string | null
  description?: string | null
  active?: boolean
  position?: number
}

export function useMenus() {
  const base = (brandId: number, venueId: number) =>
    `/brands/${brandId}/venues/${venueId}/menus`

  return {
    list: (brandId: number, venueId: number) =>
      api.get<Menu[]>(base(brandId, venueId)),
    get: (brandId: number, venueId: number, menuId: number) =>
      api.get<Menu>(`${base(brandId, venueId)}/${menuId}`),
    create: (brandId: number, venueId: number, body: MenuCreateBody) =>
      api.post<Menu>(base(brandId, venueId), body),
    update: (brandId: number, venueId: number, menuId: number, body: MenuUpdateBody) =>
      api.patch<Menu>(`${base(brandId, venueId)}/${menuId}`, body),
    remove: (brandId: number, venueId: number, menuId: number) =>
      api.del(`${base(brandId, venueId)}/${menuId}`),
  }
}
