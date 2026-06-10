<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useVenues } from '../composables/useVenues'
import { ApiError } from '../composables/useApi'
import type { Venue } from '../types'
import PageHeader from '../components/shared/PageHeader.vue'
import TabBar, { type Tab } from '../components/shared/TabBar.vue'
import VenueInfoTab from '../components/venue/VenueInfoTab.vue'
import ServingTimesPanel from '../components/serving-times/ServingTimesPanel.vue'
import OrderTypesPanel from '../components/order-types/OrderTypesPanel.vue'

const route = useRoute()
const { get } = useVenues()

const brandId = computed(() => Number(route.params.brandId))
const venueId = computed(() => Number(route.params.venueId))
const venue = ref<Venue | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)
const activeTab = ref('info')

const tabs: Tab[] = [
  { id: 'info', label: 'Info' },
  { id: 'order-types', label: 'Order Types' },
  { id: 'serving-times', label: 'Serving Times', badge: true },
]

onMounted(load)
watch([brandId, venueId], load)

async function load(): Promise<void> {
  if (!brandId.value || !venueId.value) return
  loading.value = true
  loadError.value = null
  try {
    venue.value = await get(brandId.value, venueId.value)
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

function onVenueUpdated(updated: Venue): void {
  venue.value = { ...venue.value, ...updated }
}

watch(venue, (v) => {
  if (v) document.title = `${v.name} · HoursAI`
})
</script>

<template>
  <div v-if="loading" class="state">Loading venue…</div>

  <div v-else-if="loadError || !venue" class="state state--error">
    <strong>Couldn't load venue.</strong>
    <span>{{ loadError ?? 'Venue not found' }}</span>
  </div>

  <template v-else>
    <PageHeader
      :title="venue.name"
      :subtitle="venue.address ?? undefined"
      :back-to="`/brands/${brandId}`"
      back-label="Back to brand"
    />
    <TabBar v-model="activeTab" :tabs="tabs" />

    <div class="tab-content">
      <VenueInfoTab
        v-if="activeTab === 'info'"
        :brand-id="brandId"
        :venue="venue"
        @updated="onVenueUpdated"
      />
      <OrderTypesPanel
        v-else-if="activeTab === 'order-types'"
        :brand-id="brandId"
        :venue-id="venue.id"
      />
      <ServingTimesPanel
        v-else-if="activeTab === 'serving-times'"
        parent-type="venue"
        :parent-id="venue.id"
        :entity-name="venue.name"
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
