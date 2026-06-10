<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMenus } from '../../composables/useMenus'
import { useServingTimes } from '../../composables/useServingTimes'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { Menu, ParseResult, ServingTime } from '../../types'

import AppButton from '../shared/AppButton.vue'
import AppTextarea from '../shared/AppTextarea.vue'
import ListSkeleton from '../shared/ListSkeleton.vue'
import DayChips from '../serving-times/DayChips.vue'
import ServingTimesDiff from '../serving-times/ServingTimesDiff.vue'

const props = defineProps<{ brandId: number }>()

interface MenuPreview {
  menuId: number
  menuName: string
  current: ServingTime[]
  proposed: ParseResult
  error?: string
  skipped: boolean
}

const menus = ref<Menu[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)

const selectedIds = ref<Set<number>>(new Set())
const prompt = ref('')

const parsing = ref(false)
const parseProgress = ref<{ done: number; total: number } | null>(null)
const previews = ref<MenuPreview[]>([])

const applying = ref(false)

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
    menus.value = await listMenus(props.brandId)
    selectedIds.value = new Set(menus.value.map((m) => m.id))
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

const allChecked = computed(
  () =>
    menus.value.length > 0 &&
    menus.value.every((m) => selectedIds.value.has(m.id)),
)
const someChecked = computed(() => selectedIds.value.size > 0 && !allChecked.value)

function toggleAll(): void {
  selectedIds.value = allChecked.value
    ? new Set()
    : new Set(menus.value.map((m) => m.id))
}

function toggle(id: number): void {
  const next = new Set(selectedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selectedIds.value = next
}

const targets = computed(() => menus.value.filter((m) => selectedIds.value.has(m.id)))
const canParse = computed(
  () => prompt.value.trim().length > 0 && !parsing.value && targets.value.length > 0,
)
const parseBlockedReason = computed(() => {
  if (!prompt.value.trim()) return 'Type a prompt above'
  if (!targets.value.length) return 'Select at least one menu'
  return null
})

async function onParse(): Promise<void> {
  if (!canParse.value) return
  parsing.value = true
  previews.value = []
  parseProgress.value = { done: 0, total: targets.value.length }

  const text = prompt.value.trim()
  const targetList = targets.value
  const results: MenuPreview[] = []

  const BATCH = 3
  for (let i = 0; i < targetList.length; i += BATCH) {
    const batch = targetList.slice(i, i + BATCH)
    const batchResults = await Promise.all(
      batch.map(async (menu): Promise<MenuPreview> => {
        try {
          const [current, proposed] = await Promise.all([
            listServingTimes('menu', menu.id),
            parseHours('menu', menu.id, text, menu.name),
          ])
          return {
            menuId: menu.id,
            menuName: menu.name,
            current,
            proposed,
            skipped: false,
          }
        } catch (e) {
          return {
            menuId: menu.id,
            menuName: menu.name,
            current: menu.serving_times ?? [],
            proposed: { serving_times: [], clarification_needed: false, clarification_message: null },
            error: e instanceof ApiError ? e.message : 'Network error',
            skipped: true,
          }
        }
      }),
    )
    results.push(...batchResults)
    parseProgress.value = { done: results.length, total: targetList.length }
    if (i + BATCH < targetList.length) await new Promise((r) => setTimeout(r, 400))
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
        p.proposed.serving_times.length > 0,
    ).length,
)

async function onApply(): Promise<void> {
  const toApply = previews.value.filter(
    (p) =>
      !p.skipped &&
      !p.error &&
      !p.proposed.clarification_needed &&
      p.proposed.serving_times.length > 0,
  )
  if (!toApply.length) return

  applying.value = true
  let failed = 0
  try {
    const BATCH = 5
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

/* ---- compact display helpers ---- */
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
        Select menus, describe when they're available in plain English, then review &amp; apply.
        Menu hours apply across all venues of this brand.
      </p>
    </header>

    <ListSkeleton v-if="loading" :rows="3" />

    <div v-else-if="loadError" class="state state--error">
      <strong>Couldn't load menus.</strong>
      <span>{{ loadError }}</span>
      <AppButton variant="secondary" size="sm" @click="load">Try again</AppButton>
    </div>

    <div v-else-if="!menus.length" class="state">
      <h3>No menus yet</h3>
      <p>
        This brand has no menus. Add one from
        <RouterLink :to="`/brands/${brandId}`">the Brand page</RouterLink> first.
      </p>
    </div>

    <template v-else>
      <!-- Menus table -->
      <section class="card">
        <header class="card__head">
          <label class="check">
            <input
              type="checkbox"
              :checked="allChecked"
              :indeterminate.prop="someChecked"
              @change="toggleAll"
            />
            <span class="check__box"></span>
            <span class="check__label">
              {{
                allChecked
                  ? `All ${menus.length} selected`
                  : selectedIds.size > 0
                  ? `${selectedIds.size} of ${menus.length} selected`
                  : `Select menus to configure`
              }}
            </span>
          </label>
        </header>

        <ul class="rows">
          <li
            v-for="m in menus"
            :key="m.id"
            class="row"
            :class="{ 'row--selected': selectedIds.has(m.id) }"
            @click="toggle(m.id)"
          >
            <label class="check check--row" @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.has(m.id)"
                @change="toggle(m.id)"
              />
              <span class="check__box"></span>
            </label>
            <div class="row__main">
              <RouterLink
                :to="`/brands/${brandId}/menus/${m.id}`"
                class="row__name"
                @click.stop
              >
                {{ m.name }}
              </RouterLink>
              <span v-if="m.description" class="row__sub">{{ m.description }}</span>
              <span
                v-if="m.active === false"
                class="row__inactive"
                title="Menu is currently inactive"
              >
                Inactive
              </span>
            </div>
            <div class="row__hours">
              <template v-if="(m.serving_times?.length ?? 0) === 0">
                <span class="row__hours-empty">No hours set</span>
              </template>
              <template v-else>
                <span
                  v-for="(s, i) in weekdaySlots(m.serving_times ?? [])"
                  :key="`w-${s.id ?? i}`"
                  class="row__hours-slot"
                >
                  <DayChips :model-value="s.days || []" :interactive="false" />
                  <span class="row__hours-time">{{ timeRange(s) }}</span>
                </span>
                <span
                  v-for="s in specialSlots(m.serving_times ?? [])"
                  :key="`s-${s.id}`"
                  class="row__hours-special"
                  :class="s.working ? 'row__hours-special--open' : 'row__hours-special--closed'"
                >
                  {{ s.date }}<span v-if="s.date_to"> → {{ s.date_to }}</span>
                  · {{ s.working ? 'Open' : 'Closed' }}
                </span>
              </template>
            </div>
          </li>
        </ul>
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

        <div class="templates" aria-label="Prompt templates">
          <span class="templates__label">Try:</span>
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
          :class="{ 'preview-card--skipped': p.skipped }"
        >
          <header class="preview-card__head">
            <label
              v-if="!p.error && !p.proposed.clarification_needed && p.proposed.serving_times.length"
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

            <h4 class="preview-card__name">{{ p.menuName }}</h4>

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
          <ServingTimesDiff
            v-else
            :current="p.current"
            :proposed="p.proposed.serving_times"
          />
        </article>
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
  width: 10px;
  height: 5px;
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
  width: 10px;
  height: 2px;
  background: var(--white);
}
.check__label { font-weight: var(--font-weight-semibold); color: var(--grayscale-100); }
.check--row { padding: 4px; }

.rows {
  list-style: none;
  margin: 0;
  padding: 0;
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

.row__main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.row__name {
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
  font-size: 14px;
}
.row__name:hover { color: var(--primary-accent-100); text-decoration: underline; }
.row__sub { font-size: 12px; color: var(--grayscale-50); }
.row__inactive {
  display: inline-flex;
  align-self: flex-start;
  margin-top: 2px;
  padding: 2px 6px;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  font-weight: var(--font-weight-bold);
  background: var(--grayscale-10);
  color: var(--grayscale-60);
  border-radius: var(--radius-sm);
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
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.row__hours-time { font-variant-numeric: tabular-nums; }

.row__hours-special {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  font-variant-numeric: tabular-nums;
  width: fit-content;
}
.row__hours-special--closed {
  background: var(--status-warning-15);
  color: var(--status-activating);
}
.row__hours-special--open {
  background: var(--status-success-15);
  color: var(--status-success);
}

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
</style>
