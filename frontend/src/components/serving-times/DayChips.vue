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
const DAY_INDEX: Record<DayName, number> = {
  monday: 0, tuesday: 1, wednesday: 2, thursday: 3,
  friday: 4, saturday: 5, sunday: 6,
}

const props = withDefaults(
  defineProps<{
    modelValue: DayName[]
    interactive?: boolean
  }>(),
  { interactive: true },
)

const emit = defineEmits<{ (e: 'update:modelValue', value: DayName[]): void }>()

/**
 * Collapse a list of day names into the most readable form:
 *   - 7 days → "Every day"
 *   - consecutive run → "Mon–Fri"
 *   - mixed → "Mon–Tue, Thu, Sat"
 */
const summary = computed(() => {
  if (!props.modelValue?.length) return '—'

  const seen = new Set<number>()
  for (const d of props.modelValue) {
    const i = DAY_INDEX[d]
    if (i !== undefined) seen.add(i)
  }
  if (seen.size === 0) return '—'
  if (seen.size === 7) return 'Every day'

  const indices = [...seen].sort((a, b) => a - b)
  const groups: number[][] = []
  let current: number[] = [indices[0]]
  for (let i = 1; i < indices.length; i++) {
    if (indices[i] === indices[i - 1] + 1) {
      current.push(indices[i])
    } else {
      groups.push(current)
      current = [indices[i]]
    }
  }
  groups.push(current)

  return groups
    .map((g) =>
      g.length === 1
        ? DAYS[g[0]].short
        : `${DAYS[g[0]].short}–${DAYS[g[g.length - 1]].short}`,
    )
    .join(', ')
})

function toggle(day: DayName): void {
  if (props.modelValue.includes(day)) {
    emit('update:modelValue', props.modelValue.filter((d) => d !== day))
  } else {
    emit('update:modelValue', [...props.modelValue, day])
  }
}
</script>

<template>
  <div v-if="interactive" class="chips">
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
  </div>
  <span
    v-else
    class="day-summary"
    :class="{ 'day-summary--empty': summary === '—' }"
  >
    {{ summary }}
  </span>
</template>

<style scoped>
/* Interactive editor — full strip of 7 toggleable chips */
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
.chip:hover { background: var(--grayscale-10); }
.chip--on {
  background: var(--primary-accent-100);
  color: var(--white);
}
.chip--on:hover { background: var(--primary-accent-80); }

/* Read-only summary — single compact pill with collapsed range */
.day-summary {
  display: inline-flex;
  align-items: center;
  height: 24px;
  padding: 0 12px;
  background: var(--primary-accent-04-transparent);
  color: var(--primary-accent-100);
  border-radius: 999px;
  font-size: 12px;
  font-weight: var(--font-weight-semibold);
  letter-spacing: 0.3px;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.day-summary--empty {
  background: transparent;
  color: var(--grayscale-40);
  font-style: italic;
  font-weight: var(--font-weight-normal);
  padding: 0;
}
</style>
