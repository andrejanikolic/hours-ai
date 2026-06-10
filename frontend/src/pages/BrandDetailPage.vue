<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useBrands } from '../composables/useBrands'
import { ApiError } from '../composables/useApi'
import type { Brand } from '../types'
import PageHeader from '../components/shared/PageHeader.vue'
import TabBar, { type Tab } from '../components/shared/TabBar.vue'
import VenuesTab from '../components/brand/VenuesTab.vue'
import MenusTab from '../components/brand/MenusTab.vue'
import OrderTypesTab from '../components/brand/OrderTypesTab.vue'

const route = useRoute()
const { get } = useBrands()

const brandId = computed(() => Number(route.params.brandId))
const brand = ref<Brand | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)
const activeTab = ref('venues')

const tabs: Tab[] = [
  // Info tab hidden for now — will be rebuilt to show useful serving-times info.
  { id: 'venues', label: 'Venue Opening Hours' },
  { id: 'menus', label: 'Menu Serving Times' },
  { id: 'order-types', label: 'Order Types Delivery Times' },
]

onMounted(load)
watch(brandId, load)

async function load(): Promise<void> {
  if (!brandId.value) return
  loading.value = true
  loadError.value = null
  try {
    brand.value = await get(brandId.value)
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

watch(brand, (b) => {
  if (b) document.title = `${b.name} · HoursAI`
})
</script>

<template>
  <div v-if="loading" class="state">Loading brand…</div>

  <div v-else-if="loadError || !brand" class="state state--error">
    <strong>Couldn't load brand.</strong>
    <span>{{ loadError ?? 'Brand not found' }}</span>
  </div>

  <template v-else>
    <PageHeader :title="brand.name" :subtitle="brand.slug" back-to="/" back-label="Home" />
    <TabBar v-model="activeTab" :tabs="tabs" />

    <div class="tab-content">
      <VenuesTab v-if="activeTab === 'venues'" :brand-id="brand.id" :brand-name="brand.name" />
      <MenusTab v-else-if="activeTab === 'menus'" :brand-id="brand.id" />
      <OrderTypesTab v-else-if="activeTab === 'order-types'" :brand-id="brand.id" />
    </div>
  </template>
</template>

<style scoped>
.tab-content {
  margin-top: 24px;
}

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
.state--error span {
  font-size: 13px;
  color: var(--grayscale-60);
}

.placeholder {
  padding: 32px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  color: var(--grayscale-60);
}
</style>
