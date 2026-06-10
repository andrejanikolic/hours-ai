import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/brands' },
  {
    path: '/brands',
    name: 'brands',
    component: () => import('../pages/BrandsListPage.vue'),
    meta: { title: 'Brands' },
  },
  {
    path: '/brands/:brandId',
    name: 'brand-detail',
    component: () => import('../pages/BrandDetailPage.vue'),
    props: true,
    meta: { title: 'Brand' },
  },
  {
    path: '/brands/:brandId/venues/:venueId',
    name: 'venue-detail',
    component: () => import('../pages/VenueDetailPage.vue'),
    props: true,
    meta: { title: 'Venue' },
  },
  {
    path: '/brands/:brandId/menus/:menuId',
    name: 'menu-detail',
    component: () => import('../pages/MenuDetailPage.vue'),
    props: true,
    meta: { title: 'Menu' },
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.afterEach((to) => {
  const title = (to.meta?.title as string | undefined) ?? 'HoursAI'
  document.title = `${title} · HoursAI`
})
