<script setup lang="ts">
import { computed } from 'vue'
import type { DayName, ServingTime, ServingTimeInput } from '../../types'
import DayChips from './DayChips.vue'
import StatusDot from '../shared/StatusDot.vue'

const props = defineProps<{
  current: ServingTime[]
  proposed: ServingTimeInput[]
}>()

type Status = 'kept' | 'new'

interface AfterRow {
  status: Status
  slot: ServingTime | ServingTimeInput
  key: string
}

const DAY_INDEX: Record<DayName, number> = {
  monday: 0, tuesday: 1, wednesday: 2, thursday: 3,
  friday: 4, saturday: 5, sunday: 6,
}

function slotKey(s: ServingTime | ServingTimeInput): string {
  if (s.type === 'weekday') {
    const days = [...(s.days ?? [])].sort().join(',')
    return `weekday|${days}|${s.time_from ?? ''}|${s.time_to ?? ''}|${s.working}`
  }
  return `special|${s.date ?? ''}|${s.date_to ?? ''}|${s.time_from ?? ''}|${s.time_to ?? ''}|${s.working}`
}

function sortByContent<T extends ServingTime | ServingTimeInput>(items: T[]): T[] {
  return [...items].sort((a, b) => {
    if (a.type !== b.type) return a.type === 'weekday' ? -1 : 1
    if (a.type === 'weekday') {
      const ai = Math.min(...(a.days ?? []).map((d) => DAY_INDEX[d] ?? 99))
      const bi = Math.min(...(b.days ?? []).map((d) => DAY_INDEX[d] ?? 99))
      return ai - bi
    }
    return (a.date ?? '').localeCompare(b.date ?? '')
  })
}

const currentKeys = computed(() => new Set(props.current.map(slotKey)))
const proposedKeys = computed(() => new Set(props.proposed.map(slotKey)))

const afterRows = computed<AfterRow[]>(() => {
  const rows = sortByContent(props.proposed).map<AfterRow>((slot) => ({
    status: currentKeys.value.has(slotKey(slot)) ? 'kept' : 'new',
    slot,
    key: slotKey(slot),
  }))
  return rows
})

const removedRows = computed(() =>
  sortByContent(props.current.filter((s) => !proposedKeys.value.has(slotKey(s)))),
)

const counts = computed(() => ({
  kept: afterRows.value.filter((r) => r.status === 'kept').length,
  added: afterRows.value.filter((r) => r.status === 'new').length,
  removed: removedRows.value.length,
}))

const summary = computed(() => {
  const { kept, added, removed } = counts.value
  if (kept === 0 && added === 0 && removed === 0) {
    return 'No changes — looks the same as what you have.'
  }
  if (kept > 0 && added === 0 && removed === 0) {
    return 'Looks the same as what you have.'
  }
  if (props.current.length === 0) {
    return `HoursAI will create a fresh schedule with ${added} ${added === 1 ? 'entry' : 'entries'}.`
  }
  if (added === 0 && kept === 0 && removed > 0) {
    return `Heads up — HoursAI will clear all ${removed} existing ${removed === 1 ? 'entry' : 'entries'}.`
  }

  const parts: string[] = []
  if (kept > 0) parts.push(`keep ${kept} ${kept === 1 ? 'entry' : 'entries'}`)
  if (added > 0) parts.push(`add ${added} new ${added === 1 ? 'entry' : 'entries'}`)
  if (removed > 0) parts.push(`replace ${removed} existing ${removed === 1 ? 'entry' : 'entries'}`)

  return `HoursAI will ${parts.join(', ')}.`
})

function timeLabel(s: { working: boolean; time_from: string | null | undefined; time_to: string | null | undefined }): string {
  if (!s.working) return 'Closed all day'
  if (!s.time_from || !s.time_to) return '—'
  return `${s.time_from} – ${s.time_to}`
}
</script>

<template>
  <div class="diff">
    <p class="diff__summary">{{ summary }}</p>

    <section v-if="afterRows.length" class="block block--after">
      <header class="block__head">
        <h4 class="block__title">After HoursAI applies</h4>
        <span class="block__count">
          {{ afterRows.length }} {{ afterRows.length === 1 ? 'entry' : 'entries' }}
        </span>
      </header>
      <ul class="rows">
        <li v-for="r in afterRows" :key="r.key" class="row">
          <span class="row__badge" :class="`row__badge--${r.slot.type}`">
            {{ r.slot.type === 'weekday' ? 'Weekday' : 'Special' }}
          </span>
          <div class="row__when">
            <DayChips
              v-if="r.slot.type === 'weekday'"
              :model-value="r.slot.days || []"
              :interactive="false"
            />
            <span v-else class="row__date">
              {{ r.slot.date }}{{ r.slot.date_to ? ` → ${r.slot.date_to}` : '' }}
            </span>
          </div>
          <span class="row__time">{{ timeLabel(r.slot) }}</span>
          <StatusDot
            :status="r.slot.working ? 'open' : 'closed'"
            :label="r.slot.working ? 'Open' : 'Closed'"
          />
          <span class="row__tag" :class="`row__tag--${r.status}`">
            {{ r.status === 'kept' ? 'Kept' : 'New' }}
          </span>
        </li>
      </ul>
    </section>

    <section v-if="removedRows.length" class="block block--removed">
      <header class="block__head">
        <h4 class="block__title">Will be replaced</h4>
        <span class="block__count">
          {{ removedRows.length }} {{ removedRows.length === 1 ? 'entry' : 'entries' }} going away
        </span>
      </header>
      <ul class="rows rows--dim">
        <li v-for="s in removedRows" :key="s.id" class="row">
          <span class="row__badge" :class="`row__badge--${s.type}`">
            {{ s.type === 'weekday' ? 'Weekday' : 'Special' }}
          </span>
          <div class="row__when">
            <DayChips
              v-if="s.type === 'weekday'"
              :model-value="s.days || []"
              :interactive="false"
            />
            <span v-else class="row__date">
              {{ s.date }}{{ s.date_to ? ` → ${s.date_to}` : '' }}
            </span>
          </div>
          <span class="row__time">{{ timeLabel(s) }}</span>
          <StatusDot
            :status="s.working ? 'open' : 'closed'"
            :label="s.working ? 'Open' : 'Closed'"
          />
          <span class="row__tag-spacer"></span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.diff { display: flex; flex-direction: column; gap: 16px; }

.diff__summary {
  margin: 0;
  font-size: 14px;
  color: var(--grayscale-80);
  line-height: 1.5;
}

.block {
  background: var(--white);
  border: 1px solid var(--transparent-05);
  border-radius: var(--radius-md);
  overflow: hidden;
}
.block--after   { border-color: var(--primary-accent-15); }
.block--removed { border-color: var(--status-warning-30); }

.block__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 16px;
  background: var(--grayscale-05);
  border-bottom: 1px solid var(--transparent-05);
}
.block--after .block__head   { background: var(--primary-accent-05); }
.block--removed .block__head { background: var(--status-warning-15); }

.block__title {
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-80);
}
.block--after .block__title   { color: var(--primary-accent-100); }
.block--removed .block__title { color: var(--status-activating); }

.block__count {
  font-size: 12px;
  color: var(--grayscale-60);
}

.rows {
  list-style: none;
  margin: 0;
  padding: 0;
}

.row {
  display: grid;
  grid-template-columns: 80px 1fr 140px 100px 60px;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--transparent-05);
}
.row:last-child { border-bottom: none; }

.rows--dim .row { opacity: 0.6; }

.row__badge {
  font-size: 10px;
  letter-spacing: 0.5px;
  font-weight: var(--font-weight-bold);
  text-transform: uppercase;
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  text-align: center;
}
.row__badge--weekday { background: var(--primary-accent-15); color: var(--primary-accent-100); }
.row__badge--special { background: var(--status-warning-15); color: var(--status-activating); }

.row__when { min-width: 0; }
.row__date {
  font-size: 13px;
  color: var(--grayscale-100);
  font-variant-numeric: tabular-nums;
}

.row__time {
  font-size: 13px;
  color: var(--grayscale-80);
  font-variant-numeric: tabular-nums;
}

.row__tag {
  justify-self: end;
  height: 22px;
  display: inline-flex;
  align-items: center;
  padding: 0 10px;
  font-size: 11px;
  font-weight: var(--font-weight-semibold);
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.row__tag--kept {
  background: var(--grayscale-05);
  color: var(--grayscale-60);
}
.row__tag--new {
  background: var(--primary-accent-15);
  color: var(--primary-accent-100);
}

.row__tag-spacer { display: block; }

@media (max-width: 720px) {
  .row {
    grid-template-columns: 80px 1fr auto;
    grid-template-rows: auto auto;
    row-gap: 6px;
  }
  .row__time { grid-column: 1 / -1; }
  .row__tag, .row__tag-spacer { grid-column: 3 / -1; }
}
</style>
