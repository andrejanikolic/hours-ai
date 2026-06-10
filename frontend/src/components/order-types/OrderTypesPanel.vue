<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { GLOBAL_ORDER_TYPES, useVenueOrderTypes } from '../../composables/useVenueOrderTypes'
import { ApiError } from '../../composables/useApi'
import type { VenueOrderType } from '../../types'
import AppButton from '../shared/AppButton.vue'
import ListSkeleton from '../shared/ListSkeleton.vue'
import OrderTypeCard from './OrderTypeCard.vue'

const props = defineProps<{ brandId: number; venueId: number }>()

const attached = ref<VenueOrderType[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)

const attachedMap = computed(() => {
  const m = new Map<number, VenueOrderType>()
  for (const a of attached.value) m.set(a.id, a)
  return m
})

const { list } = useVenueOrderTypes()

onMounted(load)

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    attached.value = await list(props.brandId, props.venueId)
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="panel">
    <div v-if="loading" class="panel__list">
      <ListSkeleton v-for="i in 4" :key="i" :rows="1" row-height="76px" />
    </div>

    <div v-else-if="loadError" class="state state--error">
      <strong>Couldn't load order types.</strong>
      <span>{{ loadError }}</span>
      <AppButton variant="secondary" size="sm" @click="load">Try again</AppButton>
    </div>

    <div v-else class="panel__list">
      <OrderTypeCard
        v-for="g in GLOBAL_ORDER_TYPES"
        :key="g.id"
        :order-type="g"
        :attached="attachedMap.get(g.id) ?? null"
        :brand-id="brandId"
        :venue-id="venueId"
        @changed="load"
      />
    </div>
  </div>
</template>

<style scoped>
.panel { display: flex; flex-direction: column; gap: 16px; }

.panel__list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.state {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
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
.state--error span { color: var(--grayscale-60); font-size: 13px; }
</style>
