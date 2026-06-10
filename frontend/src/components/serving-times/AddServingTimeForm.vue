<script setup lang="ts">
import { computed, ref } from 'vue'
import { useServingTimes } from '../../composables/useServingTimes'
import { ApiError } from '../../composables/useApi'
import type {
  DayName,
  ParentType,
  ServingTime,
  ServingTimeInput,
  ServingTimeType,
} from '../../types'
import AppInput from '../shared/AppInput.vue'
import AppToggle from '../shared/AppToggle.vue'
import InlineCreateForm from '../shared/InlineCreateForm.vue'
import DayChips from './DayChips.vue'

const props = defineProps<{ parentType: ParentType; parentId: number }>()
const emit = defineEmits<{
  (e: 'created', slot: ServingTime): void
  (e: 'cancel'): void
}>()

const type = ref<ServingTimeType>('weekday')
const days = ref<DayName[]>([])
const date = ref('')
const dateTo = ref('')
const timeFrom = ref('09:00')
const timeTo = ref('17:00')
const working = ref(true)

const saving = ref(false)
const fieldErrors = ref<Record<string, string>>({})
const error = ref('')

const { create } = useServingTimes()

const timeRangeError = computed(() =>
  working.value && timeFrom.value && timeTo.value && timeFrom.value >= timeTo.value
    ? 'End time must be after start time'
    : '',
)

const canSave = computed(() => {
  if (type.value === 'weekday' && days.value.length === 0) return false
  if (type.value === 'special' && !date.value) return false
  if (working.value && (!timeFrom.value || !timeTo.value)) return false
  if (timeRangeError.value) return false
  return true
})

async function save(): Promise<void> {
  if (!canSave.value) return
  error.value = ''
  fieldErrors.value = {}
  saving.value = true
  try {
    const base = {
      working: working.value,
      time_from: working.value ? timeFrom.value : null,
      time_to: working.value ? timeTo.value : null,
    }
    const slot: ServingTimeInput =
      type.value === 'weekday'
        ? { type: 'weekday', days: days.value, ...base }
        : { type: 'special', date: date.value, date_to: dateTo.value || null, ...base }

    const created = await create(props.parentType, props.parentId, slot)
    emit('created', created)
  } catch (e) {
    if (e instanceof ApiError && e.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(e.errors).map(([k, v]) => [k, v[0]]),
      )
    } else {
      error.value = e instanceof ApiError ? e.message : 'Network error'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <InlineCreateForm
    :saving="saving"
    :can-save="canSave"
    save-label="Add slot"
    @save="save"
    @cancel="$emit('cancel')"
  >
    <div class="types">
      <label class="radio">
        <input v-model="type" type="radio" value="weekday" />
        <span>Weekday schedule</span>
      </label>
      <label class="radio">
        <input v-model="type" type="radio" value="special" />
        <span>Special date</span>
      </label>
    </div>

    <div v-if="type === 'weekday'" class="field">
      <span class="field__label">Days</span>
      <DayChips v-model="days" />
      <span v-if="fieldErrors.days || (!days.length && type === 'weekday')" class="field__err">
        {{ fieldErrors.days ?? 'Pick at least one day' }}
      </span>
    </div>

    <div v-else class="grid">
      <AppInput
        v-model="date"
        label="Date"
        type="date"
        :error="fieldErrors.date"
        required
      />
      <AppInput
        v-model="dateTo"
        label="To (optional)"
        type="date"
        :error="fieldErrors.date_to"
      />
    </div>

    <div class="working-row">
      <AppToggle v-model="working" :label="working ? 'Open' : 'Closed'" />
    </div>

    <div v-if="working" class="grid">
      <AppInput
        v-model="timeFrom"
        label="From"
        type="time"
        :error="fieldErrors.time_from"
      />
      <AppInput
        v-model="timeTo"
        label="To"
        type="time"
        :error="fieldErrors.time_to || timeRangeError"
      />
    </div>

    <p v-if="error" class="form-err">{{ error }}</p>
  </InlineCreateForm>
</template>

<style scoped>
.types {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.radio {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--grayscale-80);
  cursor: pointer;
}

.radio input { accent-color: var(--primary-accent-100); }

.field { display: flex; flex-direction: column; gap: 6px; }
.field__label {
  font-size: 13px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-80);
}
.field__err { color: var(--status-error); font-size: 12px; }

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
}

.working-row { display: flex; align-items: center; }

.form-err { color: var(--status-error); font-size: 13px; margin: 0; }
</style>
