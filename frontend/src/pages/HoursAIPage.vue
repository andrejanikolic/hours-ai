<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useBrands } from '../composables/useBrands'
import { useVenues } from '../composables/useVenues'
import { ApiError } from '../composables/useApi'
import type { Brand, Venue } from '../types'
import PageHeader from '../components/shared/PageHeader.vue'
import AppButton from '../components/shared/AppButton.vue'
import HoursAIPrompt from '../components/serving-times/HoursAIPrompt.vue'

const brands = ref<Brand[]>([])
const venues = ref<Venue[]>([])
const selectedBrandId = ref<number | null>(null)
const selectedVenueId = ref<number | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)

const { list: listBrands } = useBrands()
const { list: listVenues } = useVenues()

onMounted(async () => {
  try {
    brands.value = await listBrands()
    if (brands.value.length) {
      selectedBrandId.value = brands.value[0].id
    }
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
})

watch(
  selectedBrandId,
  async (id) => {
    if (!id) {
      venues.value = []
      selectedVenueId.value = null
      return
    }
    try {
      venues.value = await listVenues(id)
      selectedVenueId.value = venues.value.length ? venues.value[0].id : null
    } catch {
      venues.value = []
      selectedVenueId.value = null
    }
  },
  { immediate: false },
)

const selectedBrand = computed(() =>
  brands.value.find((b) => b.id === selectedBrandId.value),
)
const selectedVenue = computed(() =>
  venues.value.find((v) => v.id === selectedVenueId.value),
)

const entityName = computed(() => {
  if (selectedBrand.value && selectedVenue.value) {
    return `${selectedBrand.value.name} · ${selectedVenue.value.name}`
  }
  return selectedBrand.value?.name
})

const promptKey = computed(
  () => `${selectedBrandId.value ?? 0}:${selectedVenueId.value ?? 0}`,
)
</script>

<template>
  <PageHeader
    title="HoursAI ✦"
    subtitle="Configure serving times for any venue using plain English. DeepSeek parses it, you review, then apply."
  />

  <div v-if="loading" class="state">Loading…</div>

  <div v-else-if="loadError" class="state state--error">
    <strong>Couldn't load brands.</strong>
    <span>{{ loadError }}</span>
  </div>

  <div v-else-if="!brands.length" class="state">
    <h3>No brands yet</h3>
    <p>Create a brand and add at least one venue before using HoursAI.</p>
    <RouterLink to="/brands"><AppButton>Go to Brands</AppButton></RouterLink>
  </div>

  <template v-else>
    <div class="target">
      <div class="target__field">
        <label class="target__label">Brand</label>
        <select v-model="selectedBrandId" class="target__select">
          <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>

      <div class="target__field">
        <label class="target__label">Venue</label>
        <select
          v-model="selectedVenueId"
          class="target__select"
          :disabled="!venues.length"
        >
          <option v-if="!venues.length" disabled :value="null">No venues for this brand</option>
          <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
        </select>
      </div>

      <RouterLink
        v-if="selectedBrandId && selectedVenueId"
        :to="`/brands/${selectedBrandId}/venues/${selectedVenueId}`"
        class="target__manage"
      >
        Open venue page →
      </RouterLink>
    </div>

    <div v-if="!selectedVenueId" class="state">
      <p>Pick a brand that has at least one venue to start.</p>
    </div>

    <HoursAIPrompt
      v-else
      :key="promptKey"
      parent-type="venue"
      :parent-id="selectedVenueId"
      :entity-name="entityName"
    />
  </template>
</template>

<style scoped>
.target {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
  padding: 16px 20px;
  margin-bottom: 24px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}

.target__field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 200px;
}

.target__label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-60);
}

.target__select {
  height: 36px;
  padding: 0 12px;
  background: var(--white);
  border: 1px solid var(--grayscale-20);
  border-radius: var(--radius-sm);
  color: var(--grayscale-100);
  font-family: inherit;
  font-size: 14px;
  outline: none;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.target__select:hover:not(:disabled) { border-color: var(--primary-accent-40); }
.target__select:focus { border-color: var(--primary-accent-100); box-shadow: var(--focus-ring); }
.target__select:disabled { background: var(--grayscale-05); cursor: not-allowed; }

.target__manage {
  font-size: 13px;
  color: var(--primary-accent-100);
  height: 36px;
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
}
.target__manage:hover { text-decoration: none; }

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
.state h3 { color: var(--grayscale-100); font-size: 16px; }
.state p { color: var(--grayscale-60); font-size: 13px; }
.state--error {
  background: rgba(255, 59, 48, 0.04);
  border: 1px solid rgba(255, 59, 48, 0.2);
  box-shadow: none;
  color: var(--status-error);
}
.state--error span { color: var(--grayscale-60); font-size: 13px; }
</style>
