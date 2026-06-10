<script setup lang="ts">
import { ref } from 'vue'
import { useVenueOrderTypes } from '../../composables/useVenueOrderTypes'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { OrderType, VenueOrderType } from '../../types'
import AppButton from '../shared/AppButton.vue'
import ConfirmDelete from '../shared/ConfirmDelete.vue'
import ServingTimesPanel from '../serving-times/ServingTimesPanel.vue'

const props = defineProps<{
  orderType: OrderType
  attached: VenueOrderType | null
  brandId: number
  venueId: number
}>()

const emit = defineEmits<{ (e: 'changed'): void }>()

const ICONS: Record<string, string> = {
  pickup: '🛍',
  delivery: '🛵',
  'dine-in': '🍽',
  'drive-thru': '🚗',
}

const expanded = ref(false)
const actioning = ref(false)
const confirmingDetach = ref(false)

const { attach, detach } = useVenueOrderTypes()
const toast = useToast()

async function onAttach(): Promise<void> {
  actioning.value = true
  try {
    await attach(props.brandId, props.venueId, props.orderType.id)
    toast.success(`${props.orderType.name} attached`)
    emit('changed')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Network error')
  } finally {
    actioning.value = false
  }
}

async function onConfirmDetach(): Promise<void> {
  actioning.value = true
  try {
    await detach(props.brandId, props.venueId, props.orderType.id)
    toast.success(`${props.orderType.name} detached`)
    confirmingDetach.value = false
    expanded.value = false
    emit('changed')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Network error')
    confirmingDetach.value = false
  } finally {
    actioning.value = false
  }
}
</script>

<template>
  <article class="card" :class="{ 'card--attached': !!attached }">
    <header class="card__head">
      <div class="card__title-group">
        <span class="card__icon" aria-hidden="true">{{ ICONS[orderType.slug] ?? '◯' }}</span>
        <div class="card__title-text">
          <h3 class="card__title">{{ orderType.name }}</h3>
          <span class="card__slug">{{ orderType.slug }}</span>
        </div>
      </div>

      <div class="card__head-actions">
        <span v-if="attached" class="card__pill">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          Attached
        </span>
        <AppButton v-if="!attached" size="sm" :loading="actioning" @click="onAttach">
          Attach
        </AppButton>
      </div>
    </header>

    <div v-if="attached && !confirmingDetach" class="card__row">
      <button type="button" class="card__expand" @click="expanded = !expanded">
        <span class="card__chevron" :class="{ 'card__chevron--down': expanded }">▸</span>
        Serving Times
        <span v-if="attached.serving_times?.length" class="card__count">
          {{ attached.serving_times.length }}
        </span>
      </button>
      <AppButton variant="danger" size="sm" @click="confirmingDetach = true">Detach</AppButton>
    </div>

    <ConfirmDelete
      v-if="confirmingDetach"
      :message="`Detach ${orderType.name}? Its serving times will be removed.`"
      confirm-label="Detach"
      :loading="actioning"
      @confirm="onConfirmDetach"
      @cancel="confirmingDetach = false"
    />

    <div v-if="attached && expanded" class="card__expanded">
      <ServingTimesPanel
        parent-type="order_type"
        :parent-id="attached.venue_order_type_id"
      />
    </div>
  </article>
</template>

<style scoped>
.card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  transition: box-shadow 0.15s;
}
.card--attached { box-shadow: 0 0 0 1px var(--primary-accent-15), 0 2px 5px var(--transparent-05); }

.card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.card__title-group {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.card__icon {
  font-size: 28px;
  line-height: 1;
}

.card__title-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.card__title { font-size: 16px; color: var(--grayscale-100); }
.card__slug { font-size: 12px; color: var(--grayscale-50); font-family: ui-monospace, monospace; }

.card__head-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

.card__pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 28px;
  padding: 0 10px;
  background: var(--status-success-15);
  color: var(--status-success);
  font-size: 12px;
  font-weight: var(--font-weight-semibold);
  border-radius: 999px;
}

.card__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.card__expand {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 28px;
  padding: 0 12px;
  font-size: 13px;
  font-weight: var(--font-weight-semibold);
  color: var(--primary-accent-100);
  border-radius: var(--radius-sm);
  transition: background-color 0.12s;
}
.card__expand:hover { background: var(--transparent-05); }

.card__chevron {
  display: inline-block;
  transition: transform 0.15s;
}
.card__chevron--down { transform: rotate(90deg); }

.card__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 6px;
  background: var(--primary-accent-15);
  color: var(--primary-accent-100);
  font-size: 11px;
  font-weight: var(--font-weight-bold);
  border-radius: 999px;
}

.card__expanded {
  padding-top: 16px;
  border-top: 1px solid var(--transparent-05);
}
</style>
