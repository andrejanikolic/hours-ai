<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

const API = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api'

type EntityType = 'brand' | 'venue' | 'menu' | 'order_type'

interface ServingTime {
  id?: number
  type: 'weekday' | 'special'
  days?: string[]
  date?: string
  date_to?: string
  time_from?: string
  time_to?: string
  working: boolean
}

interface Row {
  entityType: EntityType
  entityId: number
  name: string
  parentName: string
  servingTimes: ServingTime[]
}

interface PreviewResult {
  row: Row
  serving_times: ServingTime[]
  should_update: boolean
  clarification_needed: boolean
  clarification_message: string | null
  error?: string
}

const rows = ref<Row[]>([])
const loading = ref(true)
const filter = ref<EntityType | 'all'>('all')
const brandFilter = ref<string>('all')
const selected = ref<Set<string>>(new Set())
const prompt = ref('')
const previews = ref<PreviewResult[]>([])
const parsing = ref(false)
const applying = ref(false)
const applySuccess = ref(false)

const rowKey = (r: Row) => `${r.entityType}:${r.entityId}`

const brands = computed(() => [...new Set(rows.value.map(r => r.entityType === 'brand' ? r.name : r.parentName).filter(Boolean))])

const rowBrand = (r: Row) => r.entityType === 'brand' ? r.name : r.parentName

const filtered = computed(() =>
  rows.value.filter(r => {
    const typeOk = filter.value === 'all' || r.entityType === filter.value
    const brandOk = brandFilter.value === 'all' || rowBrand(r) === brandFilter.value
    return typeOk && brandOk
  })
)

const allChecked = computed(
  () => filtered.value.length > 0 && filtered.value.every(r => selected.value.has(rowKey(r)))
)

const counts = computed(() => {
  const base = brandFilter.value === 'all' ? rows.value : rows.value.filter(r => rowBrand(r) === brandFilter.value)
  return {
    all: base.length,
    brand: base.filter(r => r.entityType === 'brand').length,
    venue: base.filter(r => r.entityType === 'venue').length,
    menu: base.filter(r => r.entityType === 'menu').length,
    order_type: base.filter(r => r.entityType === 'order_type').length,
  }
})

const targets = computed(() =>
  selected.value.size > 0
    ? rows.value.filter(r => selected.value.has(rowKey(r)))
    : filtered.value
)

const canPreview = computed(() => prompt.value.trim().length > 0 && !parsing.value && targets.value.length > 0)

const previewBlockedReason = computed(() => {
  if (parsing.value) return null
  if (!prompt.value.trim()) return 'Enter a prompt below'
  if (!targets.value.length) return 'No rows match the current filter'
  return null
})

function toggleAll() {
  if (allChecked.value) {
    filtered.value.forEach(r => selected.value.delete(rowKey(r)))
  } else {
    filtered.value.forEach(r => selected.value.add(rowKey(r)))
  }
}

function toggle(r: Row) {
  const k = rowKey(r)
  selected.value.has(k) ? selected.value.delete(k) : selected.value.add(k)
}

function clearSelection() {
  selected.value.clear()
  previews.value = []
}

function weekdaySummary(times: ServingTime[]): string {
  return times
    .filter(t => t.type === 'weekday')
    .map(t => {
      const days = (t.days ?? []).map(d => d.slice(0, 3)).join(', ')
      return `${days}: ${t.working ? `${t.time_from}–${t.time_to}` : 'closed'}`
    })
    .join('  ·  ')
}

function specialDates(times: ServingTime[]): { label: string; working: boolean }[] {
  return times
    .filter(t => t.type === 'special')
    .map(t => ({
      label: t.date_to ? `${t.date} → ${t.date_to}` : (t.date ?? ''),
      working: t.working,
    }))
}

async function fetchAll() {
  loading.value = true
  const result: Row[] = []

  const brands = await fetch(`${API}/brands`).then(r => r.json())

  for (const brand of brands) {
    result.push({ entityType: 'brand', entityId: brand.id, name: brand.name, parentName: '', servingTimes: brand.serving_times ?? [] })

    const [venues, menus] = await Promise.all([
      fetch(`${API}/brands/${brand.id}/venues`).then(r => r.json()),
      fetch(`${API}/brands/${brand.id}/menus`).then(r => r.json()),
    ])

    for (const venue of venues) {
      result.push({ entityType: 'venue', entityId: venue.id, name: venue.name, parentName: brand.name, servingTimes: venue.serving_times ?? [] })
    }
    for (const menu of menus) {
      result.push({ entityType: 'menu', entityId: menu.id, name: menu.name, parentName: brand.name, servingTimes: menu.serving_times ?? [] })
    }
  }

  rows.value = result
  loading.value = false
}

async function parse() {
  if (!canPreview.value) return
  parsing.value = true
  applySuccess.value = false
  previews.value = []

  const parseTargets = targets.value

  const parseOne = async (row: Row): Promise<PreviewResult> => {
    try {
      const res = await fetch(`${API}/serving-times/parse`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ parent_type: row.entityType, parent_id: row.entityId, prompt: prompt.value, entity_name: row.name }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.message ?? 'Parse failed')
      return {
        row,
        serving_times: Array.isArray(data.preview) ? data.preview : (data.preview?.serving_times ?? []),
        should_update: data.should_update ?? true,
        clarification_needed: data.clarification_needed ?? false,
        clarification_message: data.clarification_message ?? null,
        error: undefined,
      }
    } catch (e: any) {
      return { row, serving_times: [], should_update: false, clarification_needed: false, clarification_message: null, error: e.message }
    }
  }

  const results: PreviewResult[] = []
  const BATCH = 3
  for (let i = 0; i < parseTargets.length; i += BATCH) {
    const batch = parseTargets.slice(i, i + BATCH)
    const batchResults = await Promise.all(batch.map(parseOne))
    results.push(...batchResults)
    if (i + BATCH < parseTargets.length) await new Promise(r => setTimeout(r, 500))
  }

  previews.value = results.filter(p => p.should_update !== false || p.error)
  parsing.value = false
}

async function applyAll() {
  const toApply = previews.value.filter(p => !p.clarification_needed && !p.error && p.serving_times.length)
  if (!toApply.length) return

  applying.value = true
  const BATCH = 5
  for (let i = 0; i < toApply.length; i += BATCH) {
    const batch = toApply.slice(i, i + BATCH)
    await Promise.all(
      batch.map(p =>
        fetch(`${API}/serving-times/replace`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ parent_type: p.row.entityType, parent_id: p.row.entityId, serving_times: p.serving_times }),
        })
      )
    )
    if (i + BATCH < toApply.length) await new Promise(r => setTimeout(r, 200))
  }

  applying.value = false
  applySuccess.value = true
  previews.value = []
  selected.value.clear()
  prompt.value = ''
  await fetchAll()
}

onMounted(fetchAll)
</script>

<template>
  <div class="page">

    <!-- Header -->
    <header class="header">
      <div class="header__logo">
        <span class="header__icon">🕐</span>
        <div>
          <h1 class="header__title">HoursAI</h1>
          <p class="header__sub">Set opening hours with natural language — powered by HoursAI</p>
        </div>
      </div>
    </header>

    <!-- Brand filter -->
    <div class="brand-filters">
      <button
        :class="['brand-pill', { 'brand-pill--active': brandFilter === 'all' }]"
        @click="brandFilter = 'all'; selected.clear()"
      >All brands</button>
      <button
        v-for="b in brands"
        :key="b"
        :class="['brand-pill', { 'brand-pill--active': brandFilter === b }]"
        @click="brandFilter = b; selected.clear()"
      >{{ b }}</button>
    </div>

    <!-- Filter tabs -->
    <div class="toolbar">
      <div class="filters">
        <button
          v-for="t in (['all', 'brand', 'venue', 'menu', 'order_type'] as const)"
          :key="t"
          :class="['filter-pill', { 'filter-pill--active': filter === t }]"
          @click="filter = t"
        >
          <span>{{ t === 'all' ? 'All' : t === 'order_type' ? 'Order Types' : t.charAt(0).toUpperCase() + t.slice(1) + 's' }}</span>
          <span class="filter-pill__count">{{ counts[t] }}</span>
        </button>
      </div>

      <div class="selection-actions">
        <button class="select-all-btn" @click="toggleAll">
          {{ allChecked ? 'Deselect all' : `Select all ${filtered.length}` }}
        </button>
        <transition name="fade">
          <div v-if="selected.size" class="selection-bar">
            <span class="selection-bar__text">
              <span class="selection-bar__dot" />
              {{ selected.size }} selected
            </span>
            <button class="selection-bar__clear" @click="clearSelection">Clear</button>
          </div>
        </transition>
      </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
      <table v-if="!loading">
        <thead>
          <tr>
            <th class="col-check">
              <label class="checkbox">
                <input type="checkbox" :checked="allChecked" @change="toggleAll" />
                <span class="checkbox__box" />
              </label>
            </th>
            <th>Type</th>
            <th>Name</th>
            <th>Parent</th>
            <th>Current hours</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in filtered"
            :key="rowKey(row)"
            :class="['data-row', { 'data-row--selected': selected.has(rowKey(row)) }]"
            @click="toggle(row)"
          >
            <td class="col-check" @click.stop>
              <label class="checkbox" @click.stop>
                <input type="checkbox" :checked="selected.has(rowKey(row))" @change="toggle(row)" />
                <span class="checkbox__box" />
              </label>
            </td>
            <td>
              <span :class="['badge', `badge--${row.entityType}`]">
                {{ row.entityType === 'order_type' ? 'order' : row.entityType }}
              </span>
            </td>
            <td class="col-name">{{ row.name }}</td>
            <td class="col-parent">{{ row.parentName || '—' }}</td>
            <td class="col-hours">
              <span v-if="weekdaySummary(row.servingTimes)">{{ weekdaySummary(row.servingTimes) }}</span>
              <span v-else-if="!specialDates(row.servingTimes).length" class="no-hours">No hours set</span>
              <span
                v-for="d in specialDates(row.servingTimes)"
                :key="d.label"
                :class="['date-pill', d.working ? 'date-pill--open' : 'date-pill--closed']"
              >{{ d.label }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="skeleton-wrap">
        <div v-for="i in 6" :key="i" class="skeleton-row">
          <div class="skeleton skeleton--sm" />
          <div class="skeleton skeleton--badge" />
          <div class="skeleton skeleton--md" />
          <div class="skeleton skeleton--sm" />
          <div class="skeleton skeleton--lg" />
        </div>
      </div>
    </div>

    <!-- Prompt section -->
    <div class="card prompt-card">
      <div class="prompt-card__header">
        <span class="prompt-card__icon">✨</span>
        <div>
          <p class="prompt-card__title">Describe the hours in plain English</p>
          <p class="prompt-card__hint">
            <span v-if="selected.size">Applying to <strong>{{ selected.size }} selected</strong> {{ selected.size === 1 ? 'entity' : 'entities' }}.</span>
            <span v-else>Use the filter tabs + <strong>Select all</strong> to quickly target a group.</span>
          </p>
        </div>
      </div>

      <textarea
        v-model="prompt"
        class="prompt-input"
        rows="3"
        placeholder="e.g. open every day from 09:00 to 22:00, close on Sundays&#10;or paste a full location table — HoursAI will match by name"
      />

      <div class="prompt-card__footer">
        <span class="prompt-examples">
          Try: <em @click="prompt = 'Open Mon–Fri 8am to 10pm, closed weekends'">Mon–Fri only</em>
          · <em @click="prompt = 'Open every day 10:00 to 23:00'">Every day</em>
          · <em @click="prompt = 'Closed Christmas Day (Dec 25)'">Holiday closure</em>
        </span>
        <div class="btn-wrap">
          <span v-if="previewBlockedReason" class="btn-blocked-reason">
            ← {{ previewBlockedReason }}
          </span>
          <button class="btn btn--primary" :class="{ 'btn--loading': parsing }" :disabled="!canPreview" @click="parse">
            <span v-if="parsing" class="spinner" />
            <span v-else>✨</span>
            {{ parsing ? 'Asking HoursAI…' : 'Preview with HoursAI' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Success banner -->
    <transition name="slide">
      <div v-if="applySuccess" class="banner banner--success">
        <span>✓</span>
        <span>Serving times applied successfully to {{ rows.length }} entities.</span>
      </div>
    </transition>

    <!-- Preview results -->
    <div v-if="previews.length" class="previews">
      <div class="previews__header">
        <div>
          <h2 class="previews__title">Preview</h2>
          <p class="previews__sub">Review the parsed hours before applying. Confirm they look right.</p>
        </div>
        <button
          class="btn btn--apply"
          :class="{ 'btn--loading': applying }"
          :disabled="applying || previews.every(p => p.clarification_needed || !!p.error)"
          @click="applyAll"
        >
          <span v-if="applying" class="spinner" />
          {{ applying ? 'Saving…' : `Apply to ${previews.filter(p => !p.clarification_needed && !p.error).length} entities` }}
        </button>
      </div>

      <div v-for="p in previews" :key="rowKey(p.row)" class="preview-card card">
        <div class="preview-card__header">
          <span :class="['badge', `badge--${p.row.entityType}`]">
            {{ p.row.entityType === 'order_type' ? 'order' : p.row.entityType }}
          </span>
          <strong class="preview-card__name">{{ p.row.name }}</strong>
          <span v-if="p.row.parentName" class="preview-card__parent">under {{ p.row.parentName }}</span>
          <span v-if="p.error" class="preview-card__status preview-card__status--error">Error</span>
          <span v-else-if="p.clarification_needed" class="preview-card__status preview-card__status--warn">Needs clarification</span>
          <span v-else class="preview-card__status preview-card__status--ok">Ready to apply</span>
        </div>

        <div v-if="p.error" class="alert alert--error">
          {{ p.error }}
        </div>

        <div v-else-if="p.clarification_needed" class="alert alert--warn">
          <strong>HoursAI needs more info:</strong> {{ p.clarification_message }}
        </div>

        <table v-else class="preview-table">
          <thead>
            <tr>
              <th>Schedule type</th>
              <th>Days / Date</th>
              <th>Opens</th>
              <th>Closes</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(st, i) in p.serving_times" :key="i">
              <td>
                <span :class="['type-pill', st.type === 'weekday' ? 'type-pill--weekday' : 'type-pill--special']">
                  {{ st.type }}
                </span>
              </td>
              <td class="col-mono">
                {{ st.type === 'weekday'
                  ? (st.days ?? []).map(d => d.slice(0,3)).join(', ')
                  : st.date + (st.date_to ? ' → ' + st.date_to : '') }}
              </td>
              <td class="col-mono">{{ st.time_from ?? '—' }}</td>
              <td class="col-mono">{{ st.time_to ?? '—' }}</td>
              <td>
                <span :class="['status-pill', st.working ? 'status-pill--open' : 'status-pill--closed']">
                  {{ st.working ? 'Open' : 'Closed' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* ── Layout ── */
.page {
  max-width: 1060px;
  margin: 0 auto;
  padding: 2.5rem 2rem 4rem;
}

/* ── Header ── */
.header { margin-bottom: 2rem; }
.header__logo { display: flex; align-items: center; gap: 1rem; }
.header__icon { font-size: 2.2rem; line-height: 1; }
.header__title { margin: 0; font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; }
.header__sub { margin: 0.2rem 0 0; font-size: 0.9rem; color: #94a3b8; }

/* ── Brand filter ── */
.brand-filters {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-bottom: 0.6rem;
}
.brand-pill {
  padding: 0.3rem 0.9rem;
  border-radius: 999px;
  border: 1.5px solid #e2e8f0;
  background: transparent;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.brand-pill:hover { border-color: #6366f1; color: #6366f1; }
.brand-pill--active { background: #0f172a; border-color: #0f172a; color: #fff; }

/* ── Toolbar ── */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}
.filters { display: flex; gap: 0.4rem; flex-wrap: wrap; }

.filter-pill {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.85rem;
  border-radius: 999px;
  border: 1.5px solid #e2e8f0;
  background: transparent;
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}
.filter-pill:hover { border-color: #6366f1; color: #6366f1; }
.filter-pill--active { background: #6366f1; border-color: #6366f1; color: #fff; }
.filter-pill__count {
  background: rgba(255,255,255,0.2);
  padding: 0.05rem 0.4rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}
.filter-pill:not(.filter-pill--active) .filter-pill__count {
  background: #f1f5f9;
  color: #475569;
}

.selection-actions { display: flex; align-items: center; gap: 0.5rem; }

.select-all-btn {
  padding: 0.3rem 0.75rem;
  border-radius: 6px;
  border: 1.5px solid #e2e8f0;
  background: transparent;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.select-all-btn:hover { border-color: #6366f1; color: #6366f1; background: #f5f3ff; }

.selection-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.35rem 0.75rem;
  background: #eef2ff;
  border: 1.5px solid #c7d2fe;
  border-radius: 999px;
  font-size: 0.82rem;
}
.selection-bar__dot {
  display: inline-block;
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #6366f1;
}
.selection-bar__text { display: flex; align-items: center; gap: 0.4rem; color: #4338ca; font-weight: 600; }
.selection-bar__clear { background: none; border: none; color: #6366f1; cursor: pointer; font-size: 0.82rem; padding: 0; font-weight: 500; }
.selection-bar__clear:hover { color: #4338ca; }

/* ── Card ── */
.card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
}

/* ── Table ── */
.table-card { margin-bottom: 1.25rem; }
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }

thead tr { background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; }
th { padding: 0.65rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }

.data-row { cursor: pointer; transition: background 0.1s; }
.data-row td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #1e293b; }
.data-row:last-child td { border-bottom: none; }
.data-row:hover td { background: #f8fafc; }
.data-row--selected td { background: #eef2ff; }
.data-row--selected:hover td { background: #e0e7ff; }

.col-check { width: 44px; }
.col-name { font-weight: 500; color: #0f172a; }
.col-parent { color: #94a3b8; font-size: 0.82rem; }
.col-hours { font-size: 0.8rem; color: #64748b; max-width: 480px; }
.no-hours { color: #cbd5e1; font-style: italic; }
.date-pill {
  display: inline-block;
  margin-left: 0.4rem;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
  font-family: 'SF Mono', monospace;
}
.date-pill--closed { background: #fee2e2; color: #b91c1c; }
.date-pill--open   { background: #dcfce7; color: #15803d; }

/* ── Custom checkbox ── */
.checkbox { display: flex; align-items: center; cursor: pointer; }
.checkbox input { position: absolute; opacity: 0; width: 0; height: 0; }
.checkbox__box {
  width: 16px; height: 16px;
  border: 1.5px solid #cbd5e1;
  border-radius: 4px;
  background: #fff;
  transition: all 0.1s;
  display: flex; align-items: center; justify-content: center;
}
.checkbox input:checked + .checkbox__box {
  background: #6366f1;
  border-color: #6366f1;
}
.checkbox input:checked + .checkbox__box::after {
  content: '';
  display: block;
  width: 9px; height: 5px;
  border-left: 2px solid #fff;
  border-bottom: 2px solid #fff;
  transform: rotate(-45deg) translate(1px, -1px);
}

/* ── Badges ── */
.badge {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.badge--brand      { background: #dbeafe; color: #1d4ed8; }
.badge--venue      { background: #dcfce7; color: #15803d; }
.badge--menu       { background: #fef3c7; color: #b45309; }
.badge--order_type { background: #f3e8ff; color: #7e22ce; }

/* ── Skeleton loader ── */
.skeleton-wrap { padding: 0.5rem 0; }
.skeleton-row { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; }
.skeleton { background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 6px; height: 14px; }
.skeleton--sm    { width: 60px; }
.skeleton--badge { width: 52px; height: 22px; border-radius: 6px; }
.skeleton--md    { width: 120px; }
.skeleton--lg    { width: 260px; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ── Prompt card ── */
.prompt-card { padding: 1.5rem; margin-bottom: 1.25rem; }
.prompt-card__header { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
.prompt-card__icon { font-size: 1.4rem; margin-top: 0.1rem; }
.prompt-card__title { margin: 0; font-size: 0.95rem; font-weight: 600; color: #0f172a; }
.prompt-card__hint { margin: 0.2rem 0 0; font-size: 0.82rem; color: #94a3b8; }
.prompt-card__hint--warn { color: #f59e0b; }

.prompt-input {
  width: 100%;
  box-sizing: border-box;
  padding: 0.85rem 1rem;
  border-radius: 8px;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  color: #0f172a;
  font-family: inherit;
  font-size: 0.9rem;
  line-height: 1.6;
  resize: vertical;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.prompt-input:focus {
  outline: none;
  border-color: #6366f1;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

.prompt-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.85rem;
  gap: 1rem;
  flex-wrap: wrap;
}
.btn-wrap { display: flex; align-items: center; gap: 0.6rem; }
.btn-blocked-reason { font-size: 0.8rem; color: #f59e0b; font-weight: 500; }
.prompt-examples { font-size: 0.8rem; color: #94a3b8; }
.prompt-examples em {
  font-style: normal;
  color: #6366f1;
  cursor: pointer;
  text-decoration: underline;
  text-decoration-style: dotted;
}
.prompt-examples em:hover { color: #4338ca; }

/* ── Buttons ── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.6rem 1.35rem;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 600;
  white-space: nowrap;
  transition: all 0.15s;
}
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn--primary { background: #6366f1; color: #fff; box-shadow: 0 1px 2px rgba(99,102,241,0.3); }
.btn--primary:hover:not(:disabled) { background: #4f46e5; box-shadow: 0 4px 12px rgba(99,102,241,0.35); }
.btn--apply { background: #059669; color: #fff; box-shadow: 0 1px 2px rgba(5,150,105,0.3); }
.btn--apply:hover:not(:disabled) { background: #047857; }

.spinner {
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Alerts & banners ── */
.banner {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.85rem 1.1rem;
  border-radius: 10px;
  margin-bottom: 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
}
.banner--success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }

.alert {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
}
.alert--error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
.alert--warn  { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }

/* ── Preview section ── */
.previews { margin-top: 0.5rem; }
.previews__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 1rem;
  gap: 1rem;
}
.previews__title { margin: 0; font-size: 1.1rem; font-weight: 700; color: #0f172a; }
.previews__sub   { margin: 0.2rem 0 0; font-size: 0.82rem; color: #94a3b8; }

.preview-card { padding: 1.25rem; margin-bottom: 0.75rem; }
.preview-card__header {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.preview-card__name { font-size: 0.95rem; color: #0f172a; }
.preview-card__parent { font-size: 0.82rem; color: #94a3b8; flex: 1; }

.preview-card__status {
  margin-left: auto;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
}
.preview-card__status--ok    { background: #ecfdf5; color: #065f46; }
.preview-card__status--warn  { background: #fffbeb; color: #92400e; }
.preview-card__status--error { background: #fef2f2; color: #991b1b; }

.preview-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.preview-table thead tr { background: #f8fafc; }
.preview-table th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; }
.preview-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.preview-table tr:last-child td { border-bottom: none; }
.col-mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.82rem; }

.type-pill {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 600;
}
.type-pill--weekday { background: #e0e7ff; color: #3730a3; }
.type-pill--special { background: #fce7f3; color: #9d174d; }

.status-pill {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}
.status-pill--open   { background: #dcfce7; color: #15803d; }
.status-pill--closed { background: #fee2e2; color: #b91c1c; }

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s, transform 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }
.slide-enter-active, .slide-leave-active { transition: all 0.3s; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
