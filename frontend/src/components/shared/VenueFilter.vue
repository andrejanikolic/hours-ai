<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

interface VenueOption {
  id: number
  name: string
  count: number
}

const props = defineProps<{
  venues: VenueOption[]
  /** Selected venue ids. Empty array = all venues. */
  modelValue: number[]
  label?: string
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: number[]): void }>()

const open = ref(false)
const search = ref('')
const root = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)

const selectedSet = computed(() => new Set(props.modelValue))
const totalCount = computed(() => props.venues.reduce((s, v) => s + v.count, 0))

const triggerLabel = computed(() => {
  if (props.modelValue.length === 0) return 'All venues'
  if (props.modelValue.length === 1) {
    return props.venues.find((v) => v.id === props.modelValue[0])?.name ?? '1 venue'
  }
  return `${props.modelValue.length} venues`
})
const triggerCount = computed(() =>
  props.modelValue.length === 0
    ? totalCount.value
    : props.venues.filter((v) => selectedSet.value.has(v.id)).reduce((s, v) => s + v.count, 0),
)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.venues
  return props.venues.filter((v) => v.name.toLowerCase().includes(q))
})

function toggleOpen(): void {
  open.value = !open.value
  if (open.value) {
    search.value = ''
    nextTick(() => searchInput.value?.focus())
  }
}

function selectAll(): void {
  emit('update:modelValue', [])
}

function toggleVenue(id: number): void {
  const next = new Set(props.modelValue)
  next.has(id) ? next.delete(id) : next.add(id)
  emit('update:modelValue', [...next])
}

function onDocClick(e: MouseEvent): void {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false
}

onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div ref="root" class="venue-filter">
    <div class="vselect" :class="{ 'vselect--open': open }">
      <button
        type="button"
        class="vselect__trigger"
        aria-haspopup="listbox"
        :aria-expanded="open"
        @click="toggleOpen"
      >
        <span class="vselect__value">{{ triggerLabel }}</span>
        <span class="vselect__count">{{ triggerCount }}</span>
        <span class="vselect__chev" aria-hidden="true">▾</span>
      </button>

      <div v-if="open" class="vselect__menu" role="listbox" aria-multiselectable="true">
        <div class="vselect__search">
          <span class="vselect__search-icon" aria-hidden="true">⌕</span>
          <input
            ref="searchInput"
            v-model="search"
            type="text"
            class="vselect__search-input"
            placeholder="Search venue…"
            @keydown.esc="open = false"
          />
        </div>
        <ul class="vselect__options">
          <li>
            <button
              type="button"
              class="vselect__option"
              :class="{ 'vselect__option--active': modelValue.length === 0 }"
              role="option"
              :aria-selected="modelValue.length === 0"
              @click="selectAll"
            >
              <span class="vselect__check" aria-hidden="true">✓</span>
              <span class="vselect__opt-name">All venues</span>
              <span class="vselect__opt-count">{{ totalCount }}</span>
            </button>
          </li>
          <li v-for="v in filtered" :key="v.id">
            <button
              type="button"
              class="vselect__option"
              :class="{ 'vselect__option--active': selectedSet.has(v.id) }"
              role="option"
              :aria-selected="selectedSet.has(v.id)"
              @click="toggleVenue(v.id)"
            >
              <span class="vselect__check" aria-hidden="true">✓</span>
              <span class="vselect__opt-name">{{ v.name }}</span>
              <span class="vselect__opt-count">{{ v.count }}</span>
            </button>
          </li>
          <li v-if="!filtered.length" class="vselect__empty">No venues match “{{ search }}”.</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
.venue-filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: relative;
  max-width: 320px;
}
.venue-filter__label {
  font-size: 12px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-60);
}

.vselect { position: relative; }

.vselect__trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  height: 38px;
  padding: 0 12px;
  background: var(--white);
  border: 1px solid var(--grayscale-20);
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
  cursor: pointer;
  transition: border-color 0.12s, box-shadow 0.12s;
}
.vselect__trigger:hover { border-color: var(--primary-accent-40); }
.vselect--open .vselect__trigger {
  border-color: var(--primary-accent-100);
  box-shadow: 0 0 0 3px var(--primary-accent-15);
}
.vselect__value {
  flex: 1;
  text-align: left;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.vselect__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 6px;
  background: var(--primary-accent-15);
  color: var(--primary-accent-100);
  font-size: 11px;
  font-weight: var(--font-weight-bold);
  border-radius: 999px;
  flex-shrink: 0;
}
.vselect__chev {
  color: var(--grayscale-50);
  font-size: 12px;
  transition: transform 0.15s;
  flex-shrink: 0;
}
.vselect--open .vselect__chev { transform: rotate(180deg); }

.vselect__menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  z-index: 20;
  background: var(--white);
  border: 1px solid var(--grayscale-20);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-card), 0 8px 24px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.vselect__search {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 36px;
  margin: 8px;
  padding: 0 10px;
  background: var(--grayscale-05);
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  transition: background-color 0.12s, border-color 0.12s, box-shadow 0.12s;
}
.vselect__search:focus-within {
  background: var(--white);
  border-color: var(--primary-accent-100);
}
.vselect__search-icon { color: var(--grayscale-40); font-size: 15px; flex-shrink: 0; }
.vselect__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: var(--grayscale-100);
  box-shadow: none;
}
.vselect__search-input::placeholder { color: var(--grayscale-40); }

.vselect__options {
  list-style: none;
  margin: 0;
  padding: 4px;
  max-height: 260px;
  overflow-y: auto;
}
.vselect__option {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--grayscale-100);
  cursor: pointer;
  transition: background-color 0.1s;
}
.vselect__option:hover { background: var(--grayscale-05); }
.vselect__option:focus { outline: none; }
.vselect__option:focus-visible { background: var(--grayscale-05); box-shadow: inset 0 0 0 2px var(--primary-accent-40); }
.vselect__option--active { background: var(--primary-accent-04-transparent); }
.vselect__check {
  width: 14px;
  color: var(--primary-accent-100);
  font-weight: var(--font-weight-bold);
  visibility: hidden;
  flex-shrink: 0;
}
.vselect__option--active .vselect__check { visibility: visible; }
.vselect__opt-name {
  flex: 1;
  text-align: left;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.vselect__option--active .vselect__opt-name { font-weight: var(--font-weight-semibold); }
.vselect__opt-count {
  font-size: 12px;
  font-weight: var(--font-weight-bold);
  color: var(--grayscale-50);
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
}
.vselect__empty {
  padding: 12px 10px;
  color: var(--grayscale-50);
  font-size: 13px;
  font-style: italic;
  text-align: center;
}
</style>
