<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useServingTimes } from '../../composables/useServingTimes'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { DayName, ParentType, ServingTime } from '../../types'
import AppButton from '../shared/AppButton.vue'
import StatusDot from '../shared/StatusDot.vue'
import ListSkeleton from '../shared/ListSkeleton.vue'
import DayChips from './DayChips.vue'
import AddServingTimeForm from './AddServingTimeForm.vue'

const props = defineProps<{ parentType: ParentType; parentId: number }>()

const items = ref<ServingTime[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const showCreate = ref(false)
const deletingId = ref<number | null>(null)

const { list, remove } = useServingTimes()
const toast = useToast()

const DAY_INDEX: Record<DayName, number> = {
  monday: 0, tuesday: 1, wednesday: 2, thursday: 3,
  friday: 4, saturday: 5, sunday: 6,
}

const sorted = computed(() => {
  return [...items.value].sort((a, b) => {
    if (a.type !== b.type) return a.type === 'weekday' ? -1 : 1
    if (a.type === 'weekday') {
      const ai = Math.min(...(a.days ?? []).map((d) => DAY_INDEX[d] ?? 99))
      const bi = Math.min(...(b.days ?? []).map((d) => DAY_INDEX[d] ?? 99))
      return ai - bi
    }
    return (a.date ?? '').localeCompare(b.date ?? '')
  })
})

onMounted(load)

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    items.value = await list(props.parentType, props.parentId)
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

function onCreated(slot: ServingTime): void {
  items.value = [...items.value, slot]
  showCreate.value = false
  toast.success('Slot added')
}

async function onDelete(id: number): Promise<void> {
  deletingId.value = id
  try {
    await remove(id)
    items.value = items.value.filter((i) => i.id !== id)
    toast.success('Slot removed')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Network error')
  } finally {
    deletingId.value = null
  }
}

function formatDate(d: ServingTime): string {
  if (!d.date) return ''
  return d.date_to ? `${d.date} → ${d.date_to}` : d.date
}
</script>

<template>
  <div class="manual">
    <div class="manual__head">
      <span class="manual__count">
        {{ items.length }} slot{{ items.length === 1 ? '' : 's' }}
      </span>
      <AppButton v-if="!showCreate && !loading" size="sm" @click="showCreate = true">
        + Add slot
      </AppButton>
    </div>

    <AddServingTimeForm
      v-if="showCreate"
      :parent-type="parentType"
      :parent-id="parentId"
      @created="onCreated"
      @cancel="showCreate = false"
    />

    <ListSkeleton v-if="loading" :rows="3" />

    <div v-else-if="loadError" class="state state--error">
      <strong>Couldn't load serving times.</strong>
      <span>{{ loadError }}</span>
      <AppButton variant="secondary" size="sm" @click="load">Try again</AppButton>
    </div>

    <div v-else-if="!items.length" class="state">
      <p>No serving times yet. Add one manually or describe them with HoursAI.</p>
    </div>

    <ul v-else class="slots">
      <li v-for="s in sorted" :key="s.id" class="slot">
        <span class="slot__badge" :class="`slot__badge--${s.type}`">
          {{ s.type === 'weekday' ? 'Weekday' : 'Special' }}
        </span>
        <div class="slot__when">
          <DayChips v-if="s.type === 'weekday'" :model-value="s.days || []" :interactive="false" />
          <span v-else class="slot__date">{{ formatDate(s) }}</span>
        </div>
        <span class="slot__time">
          {{ s.working && s.time_from && s.time_to ? `${s.time_from} – ${s.time_to}` : '—' }}
        </span>
        <StatusDot
          class="slot__status"
          :status="s.working ? 'open' : 'closed'"
          :label="s.working ? 'Open' : 'Closed'"
        />
        <AppButton
          variant="danger"
          size="sm"
          :loading="deletingId === s.id"
          :disabled="deletingId !== null && deletingId !== s.id"
          @click="onDelete(s.id)"
        >
          Delete
        </AppButton>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.manual { display: flex; flex-direction: column; gap: 16px; }

.manual__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.manual__count {
  font-size: 13px;
  color: var(--grayscale-60);
  font-weight: var(--font-weight-semibold);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.manual__loading { color: var(--grayscale-60); padding: 16px 0; }

.slots {
  list-style: none;
  margin: 0;
  padding: 0;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}

.slot {
  display: grid;
  grid-template-columns: 80px 1fr 140px 100px auto;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--transparent-05);
}
.slot:last-child { border-bottom: none; }

.slot__badge {
  font-size: 10px;
  letter-spacing: 0.5px;
  font-weight: var(--font-weight-bold);
  text-transform: uppercase;
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  text-align: center;
}
.slot__badge--weekday { background: var(--primary-accent-15); color: var(--primary-accent-100); }
.slot__badge--special { background: var(--status-warning-15); color: var(--status-activating); }

.slot__when { min-width: 0; }
.slot__date {
  font-size: 13px;
  color: var(--grayscale-100);
  font-variant-numeric: tabular-nums;
}

.slot__time {
  font-size: 13px;
  color: var(--grayscale-80);
  font-variant-numeric: tabular-nums;
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

@media (max-width: 720px) {
  .slot {
    grid-template-columns: 80px 1fr;
    grid-template-rows: auto auto auto;
    row-gap: 8px;
  }
  .slot__time, .slot__status, .slot button { grid-column: 1 / -1; }
}
</style>
