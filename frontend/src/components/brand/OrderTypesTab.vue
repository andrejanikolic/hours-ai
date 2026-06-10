<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useVenues } from '../../composables/useVenues'
import { useVenueOrderTypes } from '../../composables/useVenueOrderTypes'
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

/** Slugs we surface on this tab. */
const DELIVERY_SLUGS = ['delivery', 'catering-delivery']

interface Row {
  rowKey: string
  venueId: number
  venueName: string
  orderTypeId: number
  orderTypeName: string
  orderTypeSlug: string
  venueOrderTypeId: number
  current: ServingTime[]
}

interface RowPreview {
  rowKey: string
  row: Row
  proposed: ParseResult
  error?: string
  skipped: boolean
  noChange: boolean
}

const rows = ref<Row[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const page = ref(1)
const hasMore = ref(false)
const loadingMore = ref(false)

const orderTypeFilter = ref<string | 'all'>('all')
/** Selected venue ids for filtering. Empty = all venues. */
const venueFilter = ref<number[]>([])
const selectedKeys = ref<Set<string>>(new Set())
const prompt = ref('')

const parsing = ref(false)
const parseProgress = ref<{ done: number; total: number } | null>(null)
const previews = ref<RowPreview[]>([])
const applying = ref(false)

const { listPaged: listVenuesPaged } = useVenues()
const { list: listVenueOrderTypes } = useVenueOrderTypes()
const {
  parse: parseHours,
  replace: replaceHours,
  list: listServingTimes,
} = useServingTimes()
const toast = useToast()

const PROMPT_EXAMPLES = [
  'Delivery available Monday to Friday 10am to 10pm, weekends 11am to 9pm',
  'Available every day 9am to 11pm',
  'Monday to Friday 11am to 9pm, closed weekends',
  'Closed December 25 and January 1',
]

const PROMPT_TEMPLATES: { label: string; text: string }[] = [
  {
    label: 'Delivery hours',
    text: 'Delivery available Monday to Friday 10am to 10pm, Saturday and Sunday 11am to 9pm',
  },
  { label: 'Extended', text: 'Available every day 9am to 11pm' },
  {
    label: 'Weekdays only',
    text: 'Available Monday to Friday 11am to 9pm, closed weekends',
  },
  { label: 'Holiday cutoff', text: 'Closed December 25 and January 1' },
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
  page.value = 1
  try {
    const res = await listVenuesPaged(props.brandId, 1)
    const perVenue = await Promise.all(
      res.data.map((v) =>
        listVenueOrderTypes(props.brandId, v.id).then((ots) => ({
          venue: v,
          ots,
        })),
      ),
    )
    const result: Row[] = []
    for (const { venue, ots } of perVenue) {
      for (const ot of ots) {
        if (!DELIVERY_SLUGS.includes(ot.slug)) continue
        result.push({
          rowKey: `${venue.id}-${ot.id}`,
          venueId: venue.id,
          venueName: venue.name,
          orderTypeId: ot.id,
          orderTypeName: ot.name,
          orderTypeSlug: ot.slug,
          venueOrderTypeId: ot.venue_order_type_id,
          current: ot.serving_times,
        })
      }
    }
    rows.value = result
    hasMore.value = res.meta.has_more
    // Start with nothing selected — pick channels, or name a venue in the prompt.
    selectedKeys.value = new Set()
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

async function loadMore(): Promise<void> {
  loadingMore.value = true
  try {
    const res = await listVenuesPaged(props.brandId, page.value + 1)
    const perVenue = await Promise.all(
      res.data.map((v) =>
        listVenueOrderTypes(props.brandId, v.id).then((ots) => ({ venue: v, ots })),
      ),
    )
    for (const { venue, ots } of perVenue) {
      for (const ot of ots) {
        if (!DELIVERY_SLUGS.includes(ot.slug)) continue
        rows.value.push({
          rowKey: `${venue.id}-${ot.id}`,
          venueId: venue.id,
          venueName: venue.name,
          orderTypeId: ot.id,
          orderTypeName: ot.name,
          orderTypeSlug: ot.slug,
          venueOrderTypeId: ot.venue_order_type_id,
          current: ot.serving_times,
        })
      }
    }
    page.value++
    hasMore.value = res.meta.has_more
  } catch {
    // silently fail
  } finally {
    loadingMore.value = false
  }
}

/** Channel filter pills — one per delivery slug actually present, in slug order. */
const channelOptions = computed(() =>
  DELIVERY_SLUGS.map((slug) => {
    const matching = rows.value.filter((r) => r.orderTypeSlug === slug)
    return matching.length
      ? { slug, name: matching[0].orderTypeName, count: matching.length }
      : null
  }).filter((o): o is { slug: string; name: string; count: number } => o !== null),
)

/** Venue filter options — each venue with its channel count, in first-seen order. */
const venueOptions = computed(() => {
  const seen = new Map<number, { id: number; name: string; count: number }>()
  for (const r of rows.value) {
    const existing = seen.get(r.venueId)
    if (existing) existing.count++
    else seen.set(r.venueId, { id: r.venueId, name: r.venueName, count: 1 })
  }
  return [...seen.values()]
})

const filteredRows = computed(() => {
  const venueSet = new Set(venueFilter.value)
  return rows.value.filter(
    (r) =>
      (orderTypeFilter.value === 'all' || r.orderTypeSlug === orderTypeFilter.value) &&
      (venueSet.size === 0 || venueSet.has(r.venueId)),
  )
})

const visibleSelectedCount = computed(
  () => filteredRows.value.filter((r) => selectedKeys.value.has(r.rowKey)).length,
)

const allChecked = computed(
  () =>
    filteredRows.value.length > 0 &&
    filteredRows.value.every((r) => selectedKeys.value.has(r.rowKey)),
)
const someChecked = computed(
  () => visibleSelectedCount.value > 0 && !allChecked.value,
)

function toggleAll(): void {
  const next = new Set(selectedKeys.value)
  if (allChecked.value) {
    for (const r of filteredRows.value) next.delete(r.rowKey)
  } else {
    for (const r of filteredRows.value) next.add(r.rowKey)
  }
  selectedKeys.value = next
}

function toggle(key: string): void {
  const next = new Set(selectedKeys.value)
  next.has(key) ? next.delete(key) : next.add(key)
  selectedKeys.value = next
}

/**
 * Channels named in the prompt. "delivery"/"catering" are generic on this tab,
 * so detection is anchored on a venue name ("Downtown …"); it then narrows to a
 * single channel only if the prompt also says "catering" or "delivery".
 */
const detectedTargets = computed(() => {
  const p = prompt.value
  if (!p.trim()) return []
  const venueRows = filteredRows.value.filter((r) => promptMentions(p, r.venueName))
  if (!venueRows.length) return []
  const wantsCatering = promptMentions(p, 'catering')
  const wantsDelivery = promptMentions(p, 'delivery') && !wantsCatering
  return venueRows.filter((r) => {
    if (wantsCatering) return r.orderTypeSlug === 'catering-delivery'
    if (wantsDelivery) return r.orderTypeSlug === 'delivery'
    return true
  })
})

// A venue named in the prompt wins over the checkboxes; then any explicit
// selection; otherwise fall back to all currently visible channels.
const targets = computed(() => {
  if (detectedTargets.value.length) return detectedTargets.value
  const selected = filteredRows.value.filter((r) => selectedKeys.value.has(r.rowKey))
  return selected.length ? selected : filteredRows.value
})
const canParse = computed(() => prompt.value.trim().length > 0 && !parsing.value)
const parseBlockedReason = computed(() =>
  prompt.value.trim() ? null : 'Type a prompt above',
)

async function onParse(): Promise<void> {
  if (!canParse.value) return
  parsing.value = true
  previews.value = []
  parseProgress.value = { done: 0, total: targets.value.length }

  const text = prompt.value.trim()
  const list = targets.value
  const results: RowPreview[] = []

  const BATCH = 3
  for (let i = 0; i < list.length; i += BATCH) {
    const batch = list.slice(i, i + BATCH)
    const batchResults = await Promise.all(
      batch.map(async (row): Promise<RowPreview> => {
        try {
          const [current, proposed] = await Promise.all([
            listServingTimes('order_type', row.venueOrderTypeId),
            parseHours(
              'order_type',
              row.venueOrderTypeId,
              text,
              `${row.venueName} · ${row.orderTypeName}`,
            ),
          ])
          return {
            rowKey: row.rowKey,
            row: { ...row, current },
            proposed,
            skipped: false,
            noChange:
              proposed.serving_times.length > 0 &&
              sameSchedule(current, proposed.serving_times),
          }
        } catch (e) {
          return {
            rowKey: row.rowKey,
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

function toggleSkip(p: RowPreview): void {
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
      (p) =>
        !p.error &&
        !p.proposed.clarification_needed &&
        p.noChange,
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
    // 3 parallel writes (was 5) — see VenuesTab note on MySQL gap-lock deadlocks.
    const BATCH = 3
    for (let i = 0; i < toApply.length; i += BATCH) {
      const batch = toApply.slice(i, i + BATCH)
      const results = await Promise.allSettled(
        batch.map((p) =>
          replaceHours('order_type', p.row.venueOrderTypeId, p.proposed.serving_times),
        ),
      )
      failed += results.filter((r) => r.status === 'rejected').length
      if (i + BATCH < toApply.length) await new Promise((r) => setTimeout(r, 200))
    }
    const ok = toApply.length - failed
    if (ok > 0)
      toast.success(`Applied to ${ok} ${ok === 1 ? 'channel' : 'channels'}`)
    if (failed > 0)
      toast.error(`${failed} channel${failed === 1 ? '' : 's'} failed to update`)
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
function rowLabel(p: RowPreview): string {
  return `${p.row.venueName} · ${p.row.orderTypeName}`
}
</script>

<template>
  <div class="ot">
    <header class="ot__intro">
      <h3 class="ot__title">
        <span aria-hidden="true">✦</span>
        Configure delivery hours
      </h3>
      <p class="ot__subtitle">
        Set delivery times per venue for the <strong>Delivery</strong> and
        <strong>Catering Delivery</strong> channels.
      </p>
    </header>

    <ListSkeleton v-if="loading" :rows="4" />

    <div v-else-if="loadError" class="state state--error">
      <strong>Couldn't load delivery channels.</strong>
      <span>{{ loadError }}</span>
      <AppButton variant="secondary" size="sm" @click="load">Try again</AppButton>
    </div>

    <div v-else-if="!rows.length" class="state">
      <h3>No delivery channels attached</h3>
      <p>
        None of this brand's venues have Delivery or Catering Delivery attached yet.
        Attach them from each venue's Order Types tab first.
      </p>
    </div>

    <template v-else>
      <!-- Venue filter (searchable, multi-select) -->
      <VenueFilter :venues="venueOptions" v-model="venueFilter" />

      <!-- Channel filter pills -->
      <div class="channel-filter" role="tablist" aria-label="Filter by channel">
        <button
          type="button"
          class="pill"
          :class="{ 'pill--active': orderTypeFilter === 'all' }"
          role="tab"
          :aria-selected="orderTypeFilter === 'all'"
          @click="orderTypeFilter = 'all'"
        >
          All channels
          <span class="pill__count">{{ rows.length }}</span>
        </button>
        <button
          v-for="c in channelOptions"
          :key="c.slug"
          type="button"
          class="pill"
          :class="{ 'pill--active': orderTypeFilter === c.slug }"
          role="tab"
          :aria-selected="orderTypeFilter === c.slug"
          @click="orderTypeFilter = c.slug"
        >
          {{ c.name }}
          <span class="pill__count">{{ c.count }}</span>
        </button>
      </div>

      <!-- Channels table -->
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
                  : `Select channels — or just name a venue in your prompt`
              }}
            </span>
          </label>
        </header>

        <ul v-if="filteredRows.length" class="rows">
          <li
            v-for="r in filteredRows"
            :key="r.rowKey"
            class="row"
            :class="{ 'row--selected': selectedKeys.has(r.rowKey) }"
            @click="toggle(r.rowKey)"
          >
            <label class="check check--row" @click.stop>
              <input
                type="checkbox"
                :checked="selectedKeys.has(r.rowKey)"
                @change="toggle(r.rowKey)"
              />
              <span class="check__box"></span>
            </label>
            <div class="row__main">
              <span class="row__venue">{{ r.venueName }}</span>
              <span
                class="row__ot"
                :class="`row__ot--${r.orderTypeSlug}`"
              >
                {{ r.orderTypeName }}
              </span>
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
        <p v-else class="rows__empty">No channels for this filter.</p>
        <div v-if="hasMore" class="load-more">
          <AppButton variant="secondary" size="sm" :loading="loadingMore" @click="loadMore">
            Load more
          </AppButton>
        </div>
      </section>

      <!-- Prompt card -->
      <section v-if="!previews.length" class="card prompt-card">
        <header class="prompt-card__head">
          <h3 class="prompt-card__title">
            <span class="prompt-card__sparkle" aria-hidden="true">✦</span>
            Describe when delivery is available
          </h3>
          <p class="prompt-card__hint">
            HoursAI will parse your sentence and apply it to the channels you selected.
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
          <strong>{{ detectedTargets.map((r) => `${r.venueName} · ${r.orderTypeName}`).join(', ') }}</strong>
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
              Preview with HoursAI
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
              {{ previews.length === 1 ? 'channel' : 'channels' }} — nothing has changed.
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
                Review each delivery channel before applying. Uncheck any you want to skip.
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
                Apply to {{ applyableCount }}
                {{ applyableCount === 1 ? 'channel' : 'channels' }}
              </AppButton>
            </div>
          </header>

          <article
            v-for="p in previews"
            :key="p.rowKey"
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

              <h4 class="preview-card__name">{{ rowLabel(p) }}</h4>

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
              HoursAI returned the same schedule that's already saved — nothing to update for this channel.
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
.ot { display: flex; flex-direction: column; gap: 20px; }

.ot__intro { display: flex; flex-direction: column; gap: 4px; }
.ot__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  color: var(--grayscale-100);
}
.ot__title span:first-child { color: var(--primary-accent-100); font-size: 18px; }
.ot__subtitle { color: var(--grayscale-60); font-size: 13px; }
.ot__subtitle strong { color: var(--grayscale-100); font-weight: var(--font-weight-semibold); }
.load-more { display: flex; justify-content: center; padding: 12px 0; }

/* Channel filter pills */
.channel-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  padding: 0 14px;
  background: var(--white);
  border: 1px solid var(--grayscale-20);
  border-radius: 999px;
  font-size: 13px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-80);
  transition: background-color 0.12s, color 0.12s, border-color 0.12s;
}
.pill:not(.pill--active):hover { border-color: var(--primary-accent-40); color: var(--primary-accent-100); }
.pill--active {
  background: var(--primary-accent-100);
  color: var(--white);
  border-color: var(--primary-accent-100);
}
/* Keep the label readable when the active pill is hovered or focused. */
.pill--active:hover,
.pill--active:focus,
.pill--active:focus-visible {
  background: var(--primary-accent-80);
  color: var(--white);
  border-color: var(--primary-accent-80);
}
.pill__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 6px;
  background: var(--grayscale-05);
  color: var(--grayscale-60);
  font-size: 11px;
  font-weight: var(--font-weight-bold);
  border-radius: 999px;
}
.pill--active .pill__count {
  background: rgba(255, 255, 255, 0.18);
  color: var(--white);
}

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

.row__main {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex-wrap: wrap;
}
.row__venue {
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
  font-size: 14px;
}
.row__ot {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  font-size: 11px;
  font-weight: var(--font-weight-semibold);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border-radius: var(--radius-sm);
}
.row__ot--delivery {
  background: var(--primary-accent-15);
  color: var(--primary-accent-100);
}
.row__ot--catering-delivery {
  background: var(--status-warning-15);
  color: var(--status-activating);
}

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
  font-size: 14px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
  flex: 1;
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
