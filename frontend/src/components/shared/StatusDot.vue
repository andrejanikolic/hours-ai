<script setup lang="ts">
export type StatusKind =
  | 'open'
  | 'closed'
  | 'active'
  | 'inactive'
  | 'success'
  | 'error'
  | 'warning'
  | 'info'

defineProps<{
  status: StatusKind
  label?: string
}>()
</script>

<template>
  <span class="dot-row">
    <span class="dot" :class="`dot--${status}`" aria-hidden="true"></span>
    <span v-if="label" class="dot-row__label">{{ label }}</span>
  </span>
</template>

<style scoped>
.dot-row {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--grayscale-80);
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dot--open,
.dot--active,
.dot--success { background: var(--status-success); }
.dot--closed,
.dot--error    { background: var(--status-error); }
.dot--inactive { background: var(--grayscale-30); }
.dot--warning  { background: var(--status-activating); }
.dot--info     { background: var(--status-info); }
</style>
