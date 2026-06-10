<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useBrands } from '../composables/useBrands'
import { ApiError } from '../composables/useApi'
import type { Brand } from '../types'

const brands = ref<Brand[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const { list } = useBrands()

onMounted(async () => {
  try {
    brands.value = await list()
  } catch (e) {
    error.value = e instanceof ApiError ? `${e.status} · ${e.message}` : String(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="page">
    <header class="page__head">
      <h1>Brands</h1>
      <p class="page__subtitle">Phase 1 smoke test — full list page lands in Phase 3.</p>
    </header>

    <div v-if="loading" class="state">Loading brands…</div>

    <div v-else-if="error" class="state state--error">
      <strong>Couldn't load brands.</strong>
      <code>{{ error }}</code>
    </div>

    <div v-else class="state state--ok">
      Loaded <strong>{{ brands.length }}</strong> brand{{ brands.length === 1 ? '' : 's' }}.
      <ul v-if="brands.length" class="brand-list">
        <li v-for="b in brands" :key="b.id" class="brand-list__item">
          <span class="brand-list__id">#{{ b.id }}</span>
          <span class="brand-list__name">{{ b.name }}</span>
          <span class="brand-list__slug">{{ b.slug }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.page__subtitle {
  margin-top: 4px;
  color: var(--grayscale-60);
  font-size: 13px;
}

.state {
  padding: 16px 20px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  color: var(--grayscale-80);
}

.state--error {
  color: var(--status-error);
}

.state--error code {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: var(--grayscale-60);
}

.brand-list {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.brand-list__item {
  display: flex;
  gap: 12px;
  align-items: baseline;
  padding: 8px 0;
  border-bottom: 1px solid var(--transparent-05);
  font-size: 14px;
}

.brand-list__item:last-child {
  border-bottom: none;
}

.brand-list__id {
  color: var(--grayscale-50);
  font-variant-numeric: tabular-nums;
  min-width: 32px;
}

.brand-list__name {
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
}

.brand-list__slug {
  color: var(--grayscale-50);
  font-size: 12px;
}
</style>
