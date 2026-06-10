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
  return {
    list: (brandId: number) => api.get<Menu[]>(`/brands/${brandId}/menus`),
    get: (brandId: number, menuId: number) =>
      api.get<Menu>(`/brands/${brandId}/menus/${menuId}`),
    create: (brandId: number, body: MenuCreateBody) =>
      api.post<Menu>(`/brands/${brandId}/menus`, body),
    update: (brandId: number, menuId: number, body: MenuUpdateBody) =>
      api.patch<Menu>(`/brands/${brandId}/menus/${menuId}`, body),
    remove: (brandId: number, menuId: number) =>
      api.del(`/brands/${brandId}/menus/${menuId}`),
  }
}
