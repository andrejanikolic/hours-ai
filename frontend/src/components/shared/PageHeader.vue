<script setup lang="ts">
import { RouterLink } from 'vue-router'

withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    backTo?: string
    backLabel?: string
  }>(),
  { backLabel: 'Back' },
)
</script>

<template>
  <header class="page-header">
    <div class="page-header__main">
      <RouterLink v-if="backTo" :to="backTo" class="page-header__back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
        <span>{{ backLabel }}</span>
      </RouterLink>
      <h1 class="page-header__title">{{ title }}</h1>
      <p v-if="subtitle" class="page-header__subtitle">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="page-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.page-header__main {
  min-width: 0;
}

.page-header__back {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  color: var(--grayscale-60);
  margin-bottom: 8px;
}
.page-header__back:hover {
  color: var(--primary-accent-100);
  text-decoration: none;
}

.page-header__title {
  font-size: 24px;
  line-height: 1.25;
  color: var(--grayscale-100);
}

.page-header__subtitle {
  margin-top: 6px;
  color: var(--grayscale-60);
  font-size: 13px;
}

.page-header__actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}
</style>
