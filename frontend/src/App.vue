<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink, RouterView } from 'vue-router'
import parLogo from './assets/par-logo.svg'

const route = useRoute()

interface Crumb {
  label: string
  to?: string
}

const crumbs = computed<Crumb[]>(() => {
  const list: Crumb[] = [{ label: 'Brands', to: '/brands' }]
  const { brandId, venueId, menuId } = route.params as Record<string, string | undefined>

  if (brandId) {
    const brandTo = `/brands/${brandId}`
    if (venueId || menuId) {
      list.push({ label: `Brand #${brandId}`, to: brandTo })
    } else {
      list.push({ label: `Brand #${brandId}` })
    }
  }

  if (venueId) list.push({ label: `Venue #${venueId}` })
  if (menuId) list.push({ label: `Menu #${menuId}` })

  return list
})

const brandsActive = computed(() => route.path.startsWith('/brands'))
const testActive = computed(() => route.path.startsWith('/test'))
</script>

<template>
  <div class="app">
    <header class="topbar">
      <div class="topbar__left">
        <RouterLink to="/brands" class="logo" aria-label="HoursAI home">
          <img :src="parLogo" alt="PAR" class="logo__mark" />
          <span class="logo__divider" aria-hidden="true"></span>
          <span class="logo__product">HoursAI</span>
        </RouterLink>
      </div>

      <nav class="crumbs" aria-label="Breadcrumb">
        <template v-for="(crumb, i) in crumbs" :key="i">
          <span v-if="i > 0" class="crumbs__sep" aria-hidden="true">›</span>
          <RouterLink v-if="crumb.to" :to="crumb.to" class="crumbs__link">
            {{ crumb.label }}
          </RouterLink>
          <span v-else class="crumbs__current">{{ crumb.label }}</span>
        </template>
      </nav>
    </header>

    <aside class="sidebar" aria-label="Primary">
      <div class="sidebar__section-label">Hours AI</div>
      <nav class="sidebar__nav">
        <RouterLink
          to="/test"
          class="sidebar__item"
          :class="{ 'sidebar__item--active': testActive }"
        >
          <span class="sidebar__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <path d="M12 8v4l3 3"/>
            </svg>
          </span>
          <span class="sidebar__label">Store Hours</span>
        </RouterLink>
        <RouterLink
          to="/brands"
          class="sidebar__item"
          :class="{ 'sidebar__item--active': brandsActive }"
        >
          <span class="sidebar__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
              <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
          </span>
          <span class="sidebar__label">Brands</span>
        </RouterLink>
      </nav>
    </aside>

    <main class="main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.app {
  min-height: 100vh;
  background: var(--bg-page);
}

/* Top bar */
.topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: var(--white);
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 32px;
  z-index: 10;
}

.topbar__left {
  display: flex;
  align-items: center;
}

.logo {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  color: var(--primary-accent-100);
}

.logo:hover {
  text-decoration: none;
}

.logo__mark {
  height: 28px;
  width: auto;
  display: block;
}

.logo__divider {
  width: 1px;
  height: 24px;
  background: var(--grayscale-20);
}

.logo__product {
  font-size: 16px;
  font-weight: var(--font-weight-bold);
  letter-spacing: -0.2px;
  color: var(--primary-accent-100);
}

/* Breadcrumbs */
.crumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--grayscale-60);
}

.crumbs__sep {
  color: var(--grayscale-30);
}

.crumbs__link {
  color: var(--grayscale-60);
}

.crumbs__link:hover {
  color: var(--primary-accent-100);
  text-decoration: none;
}

.crumbs__current {
  color: var(--grayscale-100);
  font-weight: var(--font-weight-semibold);
}

/* Sidebar */
.sidebar {
  position: fixed;
  top: 60px;
  left: 0;
  bottom: 0;
  width: 240px;
  background: var(--grayscale-05);
  border-right: 1px solid var(--transparent-05);
  padding: 16px 12px;
  overflow-y: auto;
}

.sidebar__section-label {
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.6px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-50);
  padding: 8px 12px;
}

.sidebar__nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 4px;
}

.sidebar__item {
  display: flex;
  align-items: center;
  gap: 12px;
  height: 40px;
  padding: 0 12px;
  border-radius: var(--radius-sm);
  color: var(--grayscale-100);
  font-size: 14px;
}

.sidebar__item:hover {
  background: var(--transparent-05);
  text-decoration: none;
}

.sidebar__item--active {
  background: var(--primary-accent-07-transparent);
  color: var(--primary-accent-100);
  font-weight: var(--font-weight-semibold);
}

.sidebar__icon {
  color: var(--grayscale-50);
  display: inline-flex;
}

.sidebar__item--active .sidebar__icon {
  color: var(--primary-accent-100);
}

/* Main */
.main {
  margin-top: 60px;
  margin-left: 240px;
  padding: 24px 32px 48px;
  min-height: calc(100vh - 60px);
}
</style>
