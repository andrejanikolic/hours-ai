<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useVenues } from '../../composables/useVenues'
import { useMenus } from '../../composables/useMenus'
import { useServingTimes } from '../../composables/useServingTimes'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import { sameSchedule } from '../../composables/scheduleCompare'
import { promptMentions } from '../../composables/promptTargets'
import type { ParseResult, ServingTime } from '../../types'

import AppButton from '../shared/AppButton.vue'
import AppTextarea from '../shared/AppTextarea.vue'
import ListSkeleton from '../shared/ListSkeleton.vue'
import VoiceButton from '../shared/VoiceButton.vue'
import VenueFilter from '../shared/VenueFilter.vue'
import DayChips from '../serving-times/DayChips.vue'
import ServingTimesDiff from '../serving-times/ServingTimesDiff.vue'

const props = defineProps<{ brandId: number }>()

interface MenuRow {
  menuId: number
  menuName: string
  description: string | null
  active: boolean
  venueId: number
  venueName: string
  current: ServingTime[]
}

interface MenuPreview {
  menuId: number
  row: MenuRow
  proposed: ParseResult
  error?: string
  skipped: boolean
  noChange: boolean
}

const rows = ref<MenuRow[]>([])
const venueOptions = ref<{ id: number; name: string; count: number }[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)

/** Selected venue ids for filtering the table. Empty = all venues. */
const venueFilter = ref<number[]>([])
const selectedIds = ref<Set<number>>(new Set())
const prompt = ref('')

const parsing = ref(false)
const parseProgress = ref<{ done: number; total: number } | null>(null)
const previews = ref<MenuPreview[]>([])
const applying = ref(false)

const { list: listVenues } = useVenues()
const { list: listMenus } = useMenus()
const { parse: parseHours, replace: replaceHours, list: listServingTimes } =
  useServingTimes()
const toast = useToast()

const PROMPT_EXAMPLES = [
  'Available every day 7am to 11am',
  'Monday to Friday 11am to 3pm, weekends 11am to 4pm',
  'Every day 5pm to 10pm, Friday and Saturday until midnight',
  'Available 24/7',
]

const PROMPT_TEMPLATES: { label: string; text: string }[] = [
  { label: 'Breakfast', text: 'Available every day 7am to 11am' },
  {
    label: 'Lunch',
    text: 'Monday to Friday 11am to 3pm, weekends 11am to 4pm',
  },
  {
    label: 'Dinner',
    text: 'Every day 5pm to 10pm, Friday and Saturday until midnight',
  },
  { label: 'All day', text: 'Open every day 10am to 10pm' },
]

function applyTemplate(text: string): void {
  prompt.value = text
}

const placeholderIdx = ref(0)
let placeholderTimer: number | null = null

const placeholder = computed(() => `e.g. ${PROMPT_EXAMPLES[placeholderIdx.value]}`)

onMounted(() => {
  load()
  placeholderTimer = window.setInterval(() => {
    if (!prompt.value) {
      placeholderIdx.value = (placeholderIdx.value + 1) % PROMPT_EXAMPLES.length
    }
  }, 4000)
})

onBeforeUnmount(() => {
  if (placeholderTimer !== null) {
    window.clearInterval(placeholderTimer)
    placeholderTimer = null
  }
})

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    const venues = await listVenues(props.brandId)
    const perVenue = await Promise.all(
      venues.map((v) =>
        listMenus(props.brandId, v.id).then((menus) => ({ venue: v, menus })),
      ),
    )

    const flat: MenuRow[] = []
    const venueCounts: { id: number; name: string; count: number }[] = []
    for (const { venue, menus } of perVenue) {
      venueCounts.push({ id: venue.id, name: venue.name, count: menus.length })
      for (const m of menus) {
        flat.push({
          menuId: m.id,
          menuName: m.name,
          description: m.description,
          active: m.active,
          venueId: venue.id,
          venueName: venue.name,
          current: m.serving_times ?? [],
        })
      }
    }
    rows.value = flat
    venueOptions.value = venueCounts
    // Start with nothing selected — pick menus, or name them in the prompt.
    selectedIds.value = new Set()
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

const filteredRows = computed(() => {
  if (!venueFilter.value.length) return rows.value
  const set = new Set(venueFilter.value)
  return rows.value.filter((r) => set.has(r.venueId))
})

const visibleSelectedCount = computed(
  () => filteredRows.value.filter((r) => selectedIds.value.has(r.menuId)).length,
)

const allChecked = computed(
  () =>
    filteredRows.value.length > 0 &&
    filteredRows.value.every((r) => selectedIds.value.has(r.menuId)),
)
const someChecked = computed(
  () => visibleSelectedCount.value > 0 && !allChecked.value,
)

function toggleAll(): void {
  const next = new Set(selectedIds.value)
  if (allChecked.value) {
    for (const r of filteredRows.value) next.delete(r.menuId)
  } else {
    for (const r of filteredRows.value) next.add(r.menuId)
  }
  selectedIds.value = next
}

function toggle(id: number): void {
  const next = new Set(selectedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selectedIds.value = next
}

/** Menus named in the prompt — by menu name ("Breakfast 7–11") or venue ("Downtown"). */
const detectedTargets = computed(() => {
  if (!prompt.value.trim()) return []
  return filteredRows.value.filter(
    (r) => promptMentions(prompt.value, r.menuName) || promptMentions(prompt.value, r.venueName),
  )
})

// A name in the prompt wins over the checkboxes; otherwise fall back to selection.
const targets = computed(() =>
  detectedTargets.value.length
    ? detectedTargets.value
    : filteredRows.value.filter((r) => selectedIds.value.has(r.menuId)),
)
const canParse = computed(
  () => prompt.value.trim().length > 0 && !parsing.value && targets.value.length > 0,
)
const parseBlockedReason = computed(() => {
  if (!prompt.value.trim()) return 'Type a prompt above'
  if (!targets.value.length) return 'Select menus, or name them in your prompt'
  return null
})

async function onParse(): Promise<void> {
  if (!canParse.value) return
  parsing.value = true
  previews.value = []
  parseProgress.value = { done: 0, total: targets.value.length }

  const text = prompt.value.trim()
  const list = targets.value
  const results: MenuPreview[] = []

  const BATCH = 3
  for (let i = 0; i < list.length; i += BATCH) {
    const batch = list.slice(i, i + BATCH)
    const batchResults = await Promise.all(
      batch.map(async (row): Promise<MenuPreview> => {
        try {
          const [current, proposed] = await Promise.all([
            listServingTimes('menu', row.menuId),
            parseHours('menu', row.menuId, text, `${row.venueName} · ${row.menuName}`),
          ])
          return {
            menuId: row.menuId,
            row: { ...row, current },
            proposed,
            skipped: false,
            noChange:
              proposed.serving_times.length > 0 &&
              sameSchedule(current, proposed.serving_times),
          }
        } catch (e) {
          return {
            menuId: row.menuId,
            row,
            proposed: {
              serving_times: [],
              clarification_needed: false,
              clarification_message: null,
            },
            error: e instanceof ApiError ? e.message : 'Network error',
            skipped: true,
            noChange: false,
          }
        }
      }),
    )
    results.push(...batchResults)
    parseProgress.value = { done: results.length, total: list.length }
    if (i + BATCH < list.length) await new Promise((r) => setTimeout(r, 400))
  }

  previews.value = results
  parsing.value = false
  parseProgress.value = null
}

function toggleSkip(p: MenuPreview): void {
  p.skipped = !p.skipped
}

function onEdit(): void {
  previews.value = []
}

function onCancel(): void {
  previews.value = []
  prompt.value = ''
}

const applyableCount = computed(
  () =>
    previews.value.filter(
      (p) =>
        !p.skipped &&
        !p.error &&
        !p.proposed.clarification_needed &&
        p.proposed.serving_times.length > 0 &&
        !p.noChange,
    ).length,
)

const allNoChange = computed(
  () =>
    previews.value.length > 0 &&
    previews.value.every(
      (p) => !p.error && !p.proposed.clarification_needed && p.noChange,
    ),
)

async function onApply(): Promise<void> {
  const toApply = previews.value.filter(
    (p) =>
      !p.skipped &&
      !p.error &&
      !p.proposed.clarification_needed &&
      p.proposed.serving_times.length > 0 &&
      !p.noChange,
  )
  if (!toApply.length) return

  applying.value = true
  let failed = 0
  try {
    const BATCH = 3
    for (let i = 0; i < toApply.length; i += BATCH) {
      const batch = toApply.slice(i, i + BATCH)
      const results = await Promise.allSettled(
        batch.map((p) => replaceHours('menu', p.menuId, p.proposed.serving_times)),
      )
      failed += results.filter((r) => r.status === 'rejected').length
      if (i + BATCH < toApply.length) await new Promise((r) => setTimeout(r, 200))
    }
    const ok = toApply.length - failed
    if (ok > 0) toast.success(`Applied to ${ok} ${ok === 1 ? 'menu' : 'menus'}`)
    if (failed > 0) toast.error(`${failed} menu${failed === 1 ? '' : 's'} failed to update`)
    previews.value = []
    prompt.value = ''
    await load()
  } finally {
    applying.value = false
  }
}

/* ---- display helpers ---- */
function weekdaySlots(times: ServingTime[]) {
  return times.filter((t) => t.type === 'weekday')
}
function specialSlots(times: ServingTime[]) {
  return times.filter((t) => t.type === 'special')
}
function timeRange(s: ServingTime): string {
  if (!s.working) return 'Closed'
  if (!s.time_from || !s.time_to) return '—'
  return `${s.time_from}–${s.time_to}`
}
</script>

<template>
  <div class="menus">
    <header class="menus__intro">
      <h3 class="menus__title">
        <span aria-hidden="true">✦</span>
        Set serving times for menus
      </h3>
      <p class="menus__subtitle">
        Each venue has its own menus. Pick a venue or work across all of them.
      </p>
    </header>

    <ListSkeleton v-if="loading" :rows="4" />

    <div v-else-if="loadError" class="state state--error">
      <strong>Couldn't load menus.</strong>
      <span>{{ loadError }}</span>
      <AppButton variant="secondary" size="sm" @click="load">Try again</AppButton>
    </div>

    <div v-else-if="!rows.length" class="state">
      <h3>No menus yet</h3>
      <p>None of this brand's venues have any menus configured.</p>
    </div>

    <template v-else>
      <!-- Venue filter (searchable, multi-select) -->
      <VenueFilter :venues="venueOptions" v-model="venueFilter" />

      <!-- Menus table -->
      <section class="card">
        <header class="card__head">
          <label class="check">
            <input
              type="checkbox"
              :checked="allChecked"
              :indeterminate.prop="someChecked"
              :disabled="!filteredRows.length"
              @change="toggleAll"
            />
            <span class="check__box"></span>
            <span class="check__label">
              {{
                allChecked
                  ? `All ${filteredRows.length} selected`
                  : visibleSelectedCount > 0
                  ? `${visibleSelectedCount} of ${filteredRows.length} selected`
                  : `Select menus — or just name them in your prompt`
              }}
            </span>
          </label>
        </header>

        <ul v-if="filteredRows.length" class="rows">
          <li
            v-for="r in filteredRows"
            :key="r.menuId"
            class="row"
            :class="{ 'row--selected': selectedIds.has(r.menuId) }"
            @click="toggle(r.menuId)"
          >
            <label class="check check--row" @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.has(r.menuId)"
                @change="toggle(r.menuId)"
              />
              <span class="check__box"></span>
            </label>
            <div class="row__main">
              <span class="row__title">
                <span class="row__name">{{ r.menuName }}</span>
                <span class="row__venue-tag">{{ r.venueName }}</span>
                <span v-if="r.active === false" class="row__inactive">Inactive</span>
              </span>
              <span v-if="r.description" class="row__sub">{{ r.description }}</span>
            </div>
            <div class="row__hours">
              <template v-if="r.current.length === 0">
                <span class="row__hours-empty">No hours set</span>
              </template>
              <template v-else>
                <span
                  v-for="(s, i) in weekdaySlots(r.current)"
                  :key="`w-${s.id ?? i}`"
                  class="row__hours-slot"
                >
                  <DayChips :model-value="s.days || []" :interactive="false" />
                  <span
                    class="row__hours-time"
                    :class="{ 'row__hours-time--closed': !s.working }"
                  >
                    {{ timeRange(s) }}
                  </span>
                </span>
                <span
                  v-for="s in specialSlots(r.current)"
                  :key="`s-${s.id}`"
                  class="row__hours-slot"
                >
                  <span class="row__hours-date">
                    {{ s.date }}<template v-if="s.date_to"> → {{ s.date_to }}</template>
                  </span>
                  <span
                    class="row__hours-time"
                    :class="s.working ? 'row__hours-time--open' : 'row__hours-time--closed'"
                  >
                    {{ s.working ? 'Open' : 'Closed' }}
                  </span>
                </span>
              </template>
            </div>
          </li>
        </ul>
        <p v-else class="rows__empty">No menus for this venue yet.</p>
      </section>

      <!-- Prompt card -->
      <section v-if="!previews.length" class="card prompt-card">
        <header class="prompt-card__head">
          <h3 class="prompt-card__title">
            <span class="prompt-card__sparkle" aria-hidden="true">✦</span>
            Describe when the menu is available
          </h3>
          <p class="prompt-card__hint">
            HoursAI will parse your sentence and apply it to the menus you selected.
          </p>
        </header>

        <AppTextarea
          v-model="prompt"
          :placeholder="placeholder"
          :rows="4"
          :maxlength="1000"
          :disabled="parsing"
        />

        <p v-if="detectedTargets.length" class="auto-detect">
          <span class="auto-detect__icon" aria-hidden="true">✦</span>
          Detected from your prompt — will apply to
          <strong>{{ detectedTargets.map((r) => `${r.venueName} · ${r.menuName}`).join(', ') }}</strong>
          <span class="auto-detect__note">· table selection ignored</span>
        </p>

        <div class="templates" aria-label="Prompt templates">
          <span class="templates__label">Quick Prompts:</span>
          <template v-for="(t, i) in PROMPT_TEMPLATES" :key="t.label">
            <span v-if="i > 0" class="templates__sep" aria-hidden="true">·</span>
            <button
              type="button"
              class="templates__item"
              :disabled="parsing"
              @click="applyTemplate(t.text)"
            >
              {{ t.label }}
            </button>
          </template>
        </div>

        <div class="prompt-card__actions">
          <VoiceButton v-model="prompt" :disabled="parsing" class="prompt-card__voice" />
          <span v-if="parseBlockedReason" class="prompt-card__blocked">
            ← {{ parseBlockedReason }}
          </span>
          <AppButton
            variant="primary"
            :loading="parsing"
            :disabled="!canParse"
            @click="onParse"
          >
            <template v-if="parsing && parseProgress">
              Parsing {{ parseProgress.done }} / {{ parseProgress.total }}…
            </template>
            <template v-else-if="parsing">Asking HoursAI…</template>
            <template v-else>
              Preview with HoursAI · {{ targets.length }}
              {{ targets.length === 1 ? 'menu' : 'menus' }}
            </template>
          </AppButton>
        </div>
      </section>

      <!-- Previews -->
      <section v-if="previews.length" class="previews">
        <div v-if="allNoChange" class="no-changes">
          <span class="no-changes__icon" aria-hidden="true">✓</span>
          <div class="no-changes__text">
            <strong>Nothing to apply.</strong>
            <p>
              HoursAI returned the same schedule for all
              {{ previews.length }} selected
              {{ previews.length === 1 ? 'menu' : 'menus' }} — nothing has changed.
            </p>
          </div>
          <div class="no-changes__actions">
            <AppButton variant="ghost" @click="onCancel">Cancel</AppButton>
            <AppButton variant="secondary" @click="onEdit">Edit prompt</AppButton>
          </div>
        </div>

        <template v-else>
          <header class="previews__head">
            <div>
              <h3 class="previews__title">Preview ✦</h3>
              <p class="previews__sub">
                Review each menu before applying. Uncheck any you want to skip.
              </p>
            </div>
            <div class="previews__actions">
              <AppButton variant="ghost" :disabled="applying" @click="onCancel">
                Cancel
              </AppButton>
              <AppButton variant="secondary" :disabled="applying" @click="onEdit">
                Edit prompt
              </AppButton>
              <AppButton
                variant="primary"
                :loading="applying"
                :disabled="!applyableCount || applying"
                @click="onApply"
              >
                Apply to {{ applyableCount }} {{ applyableCount === 1 ? 'menu' : 'menus' }}
              </AppButton>
            </div>
          </header>

          <article
            v-for="p in previews"
            :key="p.menuId"
            class="card preview-card"
            :class="{
              'preview-card--skipped': p.skipped,
              'preview-card--muted': p.noChange,
            }"
          >
            <header class="preview-card__head">
              <label
                v-if="!p.error && !p.proposed.clarification_needed && p.proposed.serving_times.length && !p.noChange"
                class="check"
                @click.stop
              >
                <input
                  type="checkbox"
                  :checked="!p.skipped"
                  @change="toggleSkip(p)"
                />
                <span class="check__box"></span>
              </label>
              <span v-else class="preview-card__check-spacer"></span>

              <h4 class="preview-card__name">
                {{ p.row.menuName }}
                <span class="preview-card__venue-tag">{{ p.row.venueName }}</span>
              </h4>

              <span v-if="p.error" class="preview-card__status preview-card__status--error">
                Error
              </span>
              <span
                v-else-if="p.proposed.clarification_needed"
                class="preview-card__status preview-card__status--warn"
              >
                Needs clarification
              </span>
              <span
                v-else-if="!p.proposed.serving_times.length"
                class="preview-card__status preview-card__status--neutral"
              >
                No slots returned
              </span>
              <span
                v-else-if="p.noChange"
                class="preview-card__status preview-card__status--neutral"
              >
                No change
              </span>
              <span
                v-else-if="p.skipped"
                class="preview-card__status preview-card__status--neutral"
              >
                Skipped
              </span>
              <span v-else class="preview-card__status preview-card__status--ok">Ready</span>
            </header>

            <div v-if="p.error" class="banner banner--error">
              <strong>Couldn't parse.</strong>
              <span>{{ p.error }}</span>
            </div>
            <div v-else-if="p.proposed.clarification_needed" class="banner banner--warning">
              <strong>HoursAI needs more info.</strong>
              <span>{{ p.proposed.clarification_message ?? 'Try rephrasing the prompt.' }}</span>
            </div>
            <p v-else-if="p.noChange" class="preview-card__no-change">
              HoursAI returned the same schedule that's already saved — nothing to update for this menu.
            </p>
            <ServingTimesDiff
              v-else
              :current="p.row.current"
              :proposed="p.proposed.serving_times"
            />
          </article>
        </template>
      </section>
    </template>
  </div>
</template>

<style scoped>
.menus { display: flex; flex-direction: column; gap: 20px; }

.menus__intro { display: flex; flex-direction: column; gap: 4px; }
.menus__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  color: var(--grayscale-100);
}
.menus__title span:first-child { color: var(--primary-accent-100); font-size: 18px; }
.menus__subtitle { color: var(--grayscale-60); font-size: 13px; }

/* Card / list shared styling */
.card {
  background: var(--white);
  border: 1px solid var(--transparent-05);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}
.card__head {
  padding: 12px 16px;
  background: var(--grayscale-05);
  border-bottom: 1px solid var(--transparent-05);
}

.check {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  user-select: none;
  font-size: 13px;
  color: var(--grayscale-80);
}
.check input { position: absolute; opacity: 0; pointer-events: none; }
.check__box {
  width: 18px;
  height: 18px;
  border: 1.5px solid var(--grayscale-30);
  border-radius: 4px;
  background: var(--white);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background-color 0.12s, border-color 0.12s;
}
.check input:checked + .check__box {
  background: var(--primary-accent-100);
  border-color: var(--primary-accent-100);
}
.check input:checked + .check__box::after {
  content: '';
  width: 10px; height: 5px;
  border-left: 2px solid var(--white);
  border-bottom: 2px solid var(--white);
  transform: rotate(-45deg) translate(1px, -1px);
}
.check input:indeterminate + .check__box {
  background: var(--primary-accent-100);
  border-color: var(--primary-accent-100);
}
.check input:indeterminate + .check__box::after {
  content: '';
  width: 10px; height: 2px;
  background: var(--white);
}
.check__label { font-weight: var(--font-weight-semibold); color: var(--grayscale-100); }
.check--row { padding: 4px; }

.rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.rows__empty {
  margin: 0;
  padding: 24px;
  color: var(--grayscale-50);
  font-style: italic;
  text-align: center;
}

.row {
  display: grid;
  grid-template-columns: 32px 1fr 2fr;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--transparent-05);
  cursor: pointer;
  transition: background-color 0.12s;
}
.row:last-child { border-bottom: none; }
.row:hover { background: var(--grayscale-05); }
.row--selected { background: var(--primary-accent-04-transparent); }
.row--selected:hover { background: var(--primary-accent-07-transparent); }

.row__main { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.row__title { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.row__name {
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
  font-size: 14px;
}
.row__venue-tag {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  font-size: 10px;
  font-weight: var(--font-weight-bold);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--primary-accent-100);
  background: var(--primary-accent-15);
  border-radius: var(--radius-sm);
}
.row__inactive {
  display: inline-flex;
  padding: 2px 6px;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  font-weight: var(--font-weight-bold);
  background: var(--grayscale-10);
  color: var(--grayscale-60);
  border-radius: var(--radius-sm);
}
.row__sub { font-size: 12px; color: var(--grayscale-50); }

.row__hours {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
  color: var(--grayscale-80);
  min-width: 0;
}
.row__hours-empty {
  color: var(--grayscale-40);
  font-style: italic;
  font-size: 13px;
}
.row__hours-slot {
  display: grid;
  grid-template-columns: 150px auto;
  align-items: center;
  column-gap: 12px;
}
/* Day-summary pill sits left-aligned in its fixed column instead of stretching. */
.row__hours-slot :deep(.day-summary) { justify-self: start; }
.row__hours-time {
  justify-self: start;
  font-variant-numeric: tabular-nums;
  font-weight: 500;
  color: var(--grayscale-100);
}
.row__hours-time--closed {
  color: var(--status-error);
  font-weight: var(--font-weight-semibold);
}

/* Special-date row: date chip in the day column, status in the time column. */
.row__hours-date {
  justify-self: start;
  display: inline-flex;
  align-items: center;
  height: 24px;
  padding: 0 12px;
  background: var(--grayscale-05);
  color: var(--grayscale-80);
  border-radius: 999px;
  font-size: 12px;
  font-weight: var(--font-weight-semibold);
  letter-spacing: 0.3px;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.row__hours-time--open {
  color: var(--status-success);
  font-weight: var(--font-weight-semibold);
}

/* Prompt card */
.prompt-card {
  background: linear-gradient(
    180deg,
    var(--primary-accent-04-transparent) 0%,
    var(--white) 100%
  );
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.prompt-card__head { display: flex; flex-direction: column; gap: 4px; }
.prompt-card__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
}
.prompt-card__sparkle { color: var(--primary-accent-100); font-size: 18px; }
.prompt-card__hint { color: var(--grayscale-60); font-size: 13px; }
.prompt-card__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}
.prompt-card__blocked { color: var(--status-activating); font-size: 12px; font-weight: var(--font-weight-semibold); }
/* Voice button sits at the far left of the actions row; the rest stays right. */
.prompt-card__voice { margin-right: auto; }

/* Auto-detected targets banner */
.auto-detect {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 10px 14px;
  background: var(--primary-accent-04-transparent);
  border: 1px solid var(--primary-accent-15);
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--grayscale-80);
}
.auto-detect__icon { color: var(--primary-accent-100); }
.auto-detect strong { color: var(--primary-accent-100); font-weight: var(--font-weight-semibold); }
.auto-detect__note { color: var(--grayscale-50); font-size: 12px; }

.templates {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 4px;
  font-size: 12px;
  color: var(--grayscale-60);
}
.templates__label { font-weight: var(--font-weight-semibold); margin-right: 2px; }
.templates__sep { color: var(--grayscale-30); padding: 0 2px; }
.templates__item {
  font-size: 12px;
  color: var(--primary-accent-100);
  font-weight: var(--font-weight-semibold);
  text-decoration: underline;
  text-decoration-style: dotted;
  text-underline-offset: 3px;
  padding: 0;
  border-radius: var(--radius-sm);
  transition: color 0.12s;
}
.templates__item:hover:not(:disabled) {
  color: var(--primary-accent-80);
  text-decoration-style: solid;
}
.templates__item:disabled { opacity: 0.5; cursor: not-allowed; }

.previews { display: flex; flex-direction: column; gap: 16px; }
.previews__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.previews__title { font-size: 16px; color: var(--grayscale-100); }
.previews__sub { color: var(--grayscale-60); font-size: 13px; margin-top: 4px; }
.previews__actions { display: flex; gap: 8px; }

.preview-card {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  transition: opacity 0.15s;
}
.preview-card--skipped { opacity: 0.5; }
.preview-card--muted { opacity: 0.7; }

.preview-card__no-change {
  margin: 0;
  padding: 12px 14px;
  background: var(--grayscale-05);
  color: var(--grayscale-60);
  border-radius: var(--radius-sm);
  font-size: 13px;
  line-height: 1.5;
}

.preview-card__head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0;
  background: transparent;
  border: none;
}
.preview-card__check-spacer { width: 18px; }
.preview-card__name {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
  flex: 1;
  flex-wrap: wrap;
}
.preview-card__venue-tag {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  font-size: 10px;
  font-weight: var(--font-weight-bold);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--primary-accent-100);
  background: var(--primary-accent-15);
  border-radius: var(--radius-sm);
}
.preview-card__status {
  font-size: 11px;
  font-weight: var(--font-weight-semibold);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  padding: 4px 10px;
  border-radius: 999px;
}
.preview-card__status--ok      { background: var(--status-success-15); color: var(--status-success); }
.preview-card__status--warn    { background: var(--status-warning-15); color: var(--status-activating); }
.preview-card__status--error   { background: rgba(255, 59, 48, 0.12); color: var(--status-error); }
.preview-card__status--neutral { background: var(--grayscale-05); color: var(--grayscale-60); }

.banner {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  font-size: 13px;
}
.banner--error {
  background: rgba(255, 59, 48, 0.06);
  border: 1px solid rgba(255, 59, 48, 0.2);
  color: var(--status-error);
}
.banner--warning {
  background: var(--status-warning-15);
  border: 1px solid var(--status-warning-30);
  color: var(--grayscale-100);
}
.banner--warning strong { color: var(--status-activating); }
.banner span { color: var(--grayscale-80); font-size: 13px; }

.no-changes {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 22px;
  background: var(--status-success-15);
  border: 1px solid rgba(68, 171, 11, 0.3);
  border-radius: var(--radius-md);
  flex-wrap: wrap;
}
.no-changes__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--status-success);
  color: var(--white);
  font-weight: var(--font-weight-bold);
  flex-shrink: 0;
}
.no-changes__text { flex: 1; min-width: 240px; }
.no-changes__text strong { color: var(--status-success); font-size: 14px; }
.no-changes__text p { color: var(--grayscale-80); font-size: 13px; margin-top: 2px; }
.no-changes__actions { display: flex; gap: 8px; flex-shrink: 0; }

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

@media (max-width: 640px) {
  .row {
    grid-template-columns: 32px 1fr;
    grid-template-rows: auto auto;
    row-gap: 8px;
  }
  .row__hours { grid-column: 2 / -1; }

  .prompt-card { padding: 16px; }
  .prompt-card__actions { flex-direction: column; align-items: stretch; }

  .previews__head { flex-direction: column; align-items: stretch; }
  .previews__actions { width: 100%; }
  .previews__actions > * { flex: 1; }

  .preview-card { padding: 14px; }
  .preview-card__head { flex-wrap: wrap; }
}
</style>
