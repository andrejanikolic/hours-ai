<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useMenus } from '../composables/useMenus'
import { ApiError } from '../composables/useApi'
import type { Menu } from '../types'
import PageHeader from '../components/shared/PageHeader.vue'
import TabBar, { type Tab } from '../components/shared/TabBar.vue'
import MenuInfoTab from '../components/menu/MenuInfoTab.vue'
import ServingTimesPanel from '../components/serving-times/ServingTimesPanel.vue'

const route = useRoute()
const { get } = useMenus()

const brandId = computed(() => Number(route.params.brandId))
const menuId = computed(() => Number(route.params.menuId))
const menu = ref<Menu | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)
const activeTab = ref('info')

const tabs: Tab[] = [
  { id: 'info', label: 'Info' },
  { id: 'serving-times', label: 'Serving Times', badge: true },
]

onMounted(load)
watch([brandId, menuId], load)

async function load(): Promise<void> {
  if (!brandId.value || !menuId.value) return
  loading.value = true
  loadError.value = null
  try {
    menu.value = await get(brandId.value, menuId.value)
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

function onMenuUpdated(updated: Menu): void {
  menu.value = { ...menu.value, ...updated }
}

watch(menu, (m) => {
  if (m) document.title = `${m.name} · HoursAI`
})
</script>

<template>
  <div v-if="loading" class="state">Loading menu…</div>

  <div v-else-if="loadError || !menu" class="state state--error">
    <strong>Couldn't load menu.</strong>
    <span>{{ loadError ?? 'Menu not found' }}</span>
  </div>

  <template v-else>
    <PageHeader
      :title="menu.name"
      :subtitle="menu.description ?? undefined"
      :back-to="`/brands/${brandId}`"
      back-label="Back to brand"
    />
    <TabBar v-model="activeTab" :tabs="tabs" />

    <div class="tab-content">
      <MenuInfoTab
        v-if="activeTab === 'info'"
        :brand-id="brandId"
        :menu="menu"
        @updated="onMenuUpdated"
      />
      <ServingTimesPanel
        v-else-if="activeTab === 'serving-times'"
        parent-type="menu"
        :parent-id="menu.id"
      />
    </div>
  </template>
</template>

<style scoped>
.tab-content { margin-top: 24px; }

.state {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 24px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  color: var(--grayscale-60);
}
.state--error {
  background: rgba(255, 59, 48, 0.04);
  border: 1px solid rgba(255, 59, 48, 0.2);
  box-shadow: none;
  color: var(--status-error);
}
.state--error span { font-size: 13px; color: var(--grayscale-60); }

.placeholder {
  padding: 32px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  color: var(--grayscale-60);
}
</style>
