<script setup lang="ts">
export interface Tab {
  id: string
  label: string
  badge?: boolean | string | number
}

defineProps<{
  tabs: Tab[]
  modelValue: string
}>()

defineEmits<{ (e: 'update:modelValue', id: string): void }>()
</script>

<template>
  <div class="tabs" role="tablist">
    <button
      v-for="t in tabs"
      :key="t.id"
      type="button"
      role="tab"
      :aria-selected="modelValue === t.id"
      class="tabs__tab"
      :class="{ 'tabs__tab--active': modelValue === t.id }"
      @click="$emit('update:modelValue', t.id)"
    >
      <span>{{ t.label }}</span>
      <span v-if="t.badge === true" class="tabs__dot" aria-hidden="true"></span>
      <span v-else-if="t.badge !== false && t.badge != null" class="tabs__badge">
        {{ t.badge }}
      </span>
    </button>
  </div>
</template>

<style scoped>
.tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--grayscale-10);
  overflow-x: auto;
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.tabs::-webkit-scrollbar { display: none; }

.tabs__tab {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  flex-shrink: 0;
  font-size: 14px;
  color: var(--grayscale-60);
  font-weight: var(--font-weight-semibold);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: color 0.15s, border-color 0.15s;
}
.tabs__tab:hover { color: var(--grayscale-100); }
.tabs__tab--active {
  color: var(--primary-accent-100);
  border-bottom-color: var(--primary-accent-100);
}

.tabs__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--primary-accent-100);
}
.tabs__badge {
  background: var(--primary-accent-15);
  color: var(--primary-accent-100);
  font-size: 11px;
  font-weight: var(--font-weight-bold);
  padding: 1px 6px;
  border-radius: 999px;
  min-width: 18px;
  text-align: center;
}
</style>
