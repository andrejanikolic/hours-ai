<script setup lang="ts">
import type { ServingTimeInput } from '../../types'
import DayChips from './DayChips.vue'
import StatusDot from '../shared/StatusDot.vue'

defineProps<{ items: ServingTimeInput[] }>()
</script>

<template>
  <div class="preview">
    <p v-if="!items.length" class="preview__empty">
      The model didn't return any slots. Try rephrasing your prompt.
    </p>
    <ul v-else class="preview__list">
      <li v-for="(s, i) in items" :key="i" class="row">
        <span class="row__badge" :class="`row__badge--${s.type}`">
          {{ s.type === 'weekday' ? 'Weekday' : 'Special' }}
        </span>
        <div class="row__when">
          <DayChips v-if="s.type === 'weekday'" :model-value="s.days || []" :interactive="false" />
          <span v-else class="row__date">
            {{ s.date }}{{ s.date_to ? ` → ${s.date_to}` : '' }}
          </span>
        </div>
        <span class="row__time">
          {{ s.working && s.time_from && s.time_to ? `${s.time_from} – ${s.time_to}` : '—' }}
        </span>
        <StatusDot
          :status="s.working ? 'open' : 'closed'"
          :label="s.working ? 'Open' : 'Closed'"
        />
      </li>
    </ul>
  </div>
</template>

<style scoped>
.preview { margin: 0; }

.preview__empty {
  margin: 0;
  padding: 20px;
  color: var(--grayscale-60);
  background: var(--grayscale-05);
  border-radius: var(--radius-sm);
  font-size: 13px;
}

.preview__list {
  list-style: none;
  margin: 0;
  padding: 0;
  border-radius: var(--radius-sm);
  background: var(--grayscale-05);
  overflow: hidden;
}

.row {
  display: grid;
  grid-template-columns: 80px 1fr 140px 100px;
  align-items: center;
  gap: 16px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--transparent-05);
}
.row:last-child { border-bottom: none; }

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

@media (max-width: 720px) {
  .row {
    grid-template-columns: 80px 1fr;
    grid-template-rows: auto auto;
    row-gap: 6px;
  }
  .row__time, .row__status { grid-column: 1 / -1; }
}
</style>
