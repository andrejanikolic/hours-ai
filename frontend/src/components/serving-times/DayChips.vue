<script setup lang="ts">
import { computed } from 'vue'
import type { DayName } from '../../types'

interface DayDef {
  id: DayName
  short: string
}

const DAYS: DayDef[] = [
  { id: 'monday', short: 'Mon' },
  { id: 'tuesday', short: 'Tue' },
  { id: 'wednesday', short: 'Wed' },
  { id: 'thursday', short: 'Thu' },
  { id: 'friday', short: 'Fri' },
  { id: 'saturday', short: 'Sat' },
  { id: 'sunday', short: 'Sun' },
]

const props = withDefaults(
  defineProps<{
    modelValue: DayName[]
    interactive?: boolean
  }>(),
  { interactive: true },
)

const emit = defineEmits<{ (e: 'update:modelValue', value: DayName[]): void }>()

const selectedInOrder = computed(() =>
  DAYS.filter((d) => props.modelValue.includes(d.id)),
)

function toggle(day: DayName): void {
  if (props.modelValue.includes(day)) {
    emit('update:modelValue', props.modelValue.filter((d) => d !== day))
  } else {
    emit('update:modelValue', [...props.modelValue, day])
  }
}
</script>

<template>
  <div class="chips" :class="{ 'chips--readonly': !interactive }">
    <template v-if="interactive">
      <button
        v-for="d in DAYS"
        :key="d.id"
        type="button"
        class="chip"
        :class="{ 'chip--on': modelValue.includes(d.id) }"
        @click="toggle(d.id)"
      >
        {{ d.short }}
      </button>
    </template>
    <template v-else>
      <span v-if="!selectedInOrder.length" class="chips__empty">—</span>
      <span
        v-for="d in selectedInOrder"
        :key="d.id"
        class="chip chip--on chip--readonly"
      >
        {{ d.short }}
      </span>
    </template>
  </div>
</template>

<style scoped>
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chip {
  height: 28px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: var(--font-weight-semibold);
  background: var(--grayscale-05);
  color: var(--grayscale-80);
  border: 1px solid transparent;
  transition: background-color 0.12s, color 0.12s, border-color 0.12s;
}

button.chip:hover { background: var(--grayscale-10); }

.chip--on {
  background: var(--primary-accent-100);
  color: var(--white);
}
button.chip--on:hover { background: var(--primary-accent-80); }

.chip--readonly {
  height: 22px;
  padding: 0 8px;
  font-size: 11px;
  letter-spacing: 0.3px;
  cursor: default;
}

.chips__empty { color: var(--grayscale-40); font-size: 13px; }
</style>
