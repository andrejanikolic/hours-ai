<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, RouterLink, RouterView } from 'vue-router'
import parLogo from './assets/par-logo.svg'
import ToastStack from './components/shared/ToastStack.vue'
import { useBrands } from './composables/useBrands'
import type { Brand } from './types'

const route = useRoute()
const { list: listBrands } = useBrands()

const brands = ref<Brand[]>([])
const brandsLoading = ref(true)
const sidebarOpen = ref(false)

async function refreshBrands(): Promise<void> {
  try {
    brands.value = await listBrands()
  } catch {
    /* keep previous list on transient failure */
  } finally {
    brandsLoading.value = false
  }
}

onMounted(refreshBrands)

// Refresh brand list on every navigation + auto-close drawer when route changes.
watch(
  () => route.fullPath,
  () => {
    refreshBrands()
    sidebarOpen.value = false
  },
)

// Lock page scroll when drawer is open (mobile UX standard).
watch(sidebarOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})

// Restore body scroll on unmount (HMR safety).
onBeforeUnmount(() => {
  document.body.style.overflow = ''
})

function toggleSidebar(): void {
  sidebarOpen.value = !sidebarOpen.value
}

const activeBrandId = computed(() => {
  const id = (route.params as Record<string, string | undefined>).brandId
  return id ? Number(id) : null
})

interface Crumb {
  label: string
  to?: string
}

const crumbs = computed<Crumb[]>(() => {
  const { brandId, venueId, menuId } = route.params as Record<string, string | undefined>
  if (!brandId) return []

  const brand = brands.value.find((b) => b.id === Number(brandId))
  const brandLabel = brand?.name ?? `Brand #${brandId}`
  const list: Crumb[] = []

  if (venueId || menuId) {
    list.push({ label: brandLabel, to: `/brands/${brandId}` })
  } else {
    list.push({ label: brandLabel })
  }

  if (venueId) list.push({ label: `Venue #${venueId}` })
  if (menuId) list.push({ label: `Menu #${menuId}` })

  return list
})

</script>

<template>
  <div class="app">
    <header class="topbar">
      <button
        type="button"
        class="topbar__hamburger"
        :aria-expanded="sidebarOpen"
        aria-controls="primary-sidebar"
        aria-label="Toggle navigation"
        @click="toggleSidebar"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>

      <div class="topbar__left">
        <RouterLink to="/" class="logo" aria-label="HoursAI home">
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

    <div
      class="backdrop"
      :class="{ 'backdrop--visible': sidebarOpen }"
      aria-hidden="true"
      @click="sidebarOpen = false"
    ></div>

    <aside
      id="primary-sidebar"
      class="sidebar"
      :class="{ 'sidebar--open': sidebarOpen }"
      aria-label="Primary">
      <div class="sidebar__section-label">Brands</div>
      <nav class="sidebar__nav">
        <p v-if="brandsLoading && !brands.length" class="sidebar__hint">Loading…</p>
        <p v-else-if="!brands.length" class="sidebar__hint">
          No brands yet.
        </p>
        <RouterLink
          v-for="b in brands"
          :key="b.id"
          :to="`/brands/${b.id}`"
          class="sidebar__item"
          :class="{ 'sidebar__item--active': activeBrandId === b.id }"
        >
          <span class="sidebar__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
              <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
          </span>
          <span class="sidebar__label">{{ b.name }}</span>
        </RouterLink>
      </nav>
    </aside>

    <main class="main">
      <div class="main__inner">
        <RouterView />
      </div>
    </main>

    <ToastStack />
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

.topbar__hamburger {
  display: none;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: var(--radius-sm);
  color: var(--grayscale-80);
  background: transparent;
  transition: background-color 0.12s, color 0.12s;
}
.topbar__hamburger:hover { background: var(--grayscale-05); color: var(--grayscale-100); }
.topbar__hamburger:focus-visible { box-shadow: var(--focus-ring); }

.backdrop {
  position: fixed;
  inset: 60px 0 0 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 20;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.22s ease;
}
.backdrop--visible {
  opacity: 1;
  pointer-events: auto;
}
@media (min-width: 769px) {
  .backdrop { display: none; }
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

.sidebar__hint {
  padding: 8px 12px;
  color: var(--grayscale-50);
  font-size: 13px;
  font-style: italic;
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
  min-height: calc(100vh - 60px);
}

.main__inner {
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 32px 48px;
}

/* ----- Mobile (≤768px) ----- */
@media (max-width: 768px) {
  .topbar {
    padding: 0 12px;
    gap: 12px;
  }
  .topbar__hamburger { display: inline-flex; }
  .logo__product { font-size: 15px; }
  .logo__divider { display: none; }
  .crumbs {
    font-size: 12px;
    gap: 4px;
    overflow-x: auto;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  .crumbs::-webkit-scrollbar { display: none; }
  .crumbs__current { font-weight: var(--font-weight-semibold); }

  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.22s ease;
    width: 260px;
    z-index: 30;
    box-shadow: var(--shadow-modal);
  }
  .sidebar--open { transform: translateX(0); }

  .main { margin-left: 0; }
  .main__inner { padding: 16px 16px 40px; }
}
</style>
