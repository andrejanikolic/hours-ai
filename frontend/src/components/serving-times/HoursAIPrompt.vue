<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useServingTimes } from '../../composables/useServingTimes'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { ParentType, ParseResult } from '../../types'
import AppButton from '../shared/AppButton.vue'
import AppTextarea from '../shared/AppTextarea.vue'
import ConfirmDelete from '../shared/ConfirmDelete.vue'
import ServingTimesPreview from './ServingTimesPreview.vue'

const props = defineProps<{ parentType: ParentType; parentId: number }>()
const emit = defineEmits<{ (e: 'applied'): void }>()

const PROMPT_EXAMPLES = [
  "We're open Mon-Fri 8am to 10pm, closed weekends.",
  'Saturday 9am to 11pm, Sunday 10am to 8pm.',
  "Closed Christmas Day and New Year's Day.",
  'Open weekdays 8am-10pm, weekends 9am-11pm.',
]

const promptText = ref('')
const placeholderIdx = ref(0)
let placeholderTimer: number | null = null

const parsing = ref(false)
const parseError = ref('')
const result = ref<ParseResult | null>(null)

const applying = ref(false)
const applyError = ref('')
const confirmingApply = ref(false)

const { parse, replace } = useServingTimes()
const toast = useToast()

const canParse = computed(() => promptText.value.trim().length > 0 && !parsing.value)
const canApply = computed(() => !!result.value && result.value.serving_times.length > 0)
const placeholder = computed(() => `e.g. "${PROMPT_EXAMPLES[placeholderIdx.value]}"`)

onMounted(() => {
  placeholderTimer = window.setInterval(() => {
    if (!promptText.value) {
      placeholderIdx.value = (placeholderIdx.value + 1) % PROMPT_EXAMPLES.length
    }
  }, 4000)
})

onBeforeUnmount(() => {
  if (placeholderTimer !== null) window.clearInterval(placeholderTimer)
})

async function onParse(): Promise<void> {
  if (!canParse.value) return
  parseError.value = ''
  result.value = null
  parsing.value = true
  try {
    result.value = await parse(
      props.parentType,
      props.parentId,
      promptText.value.trim(),
    )
  } catch (e) {
    parseError.value =
      e instanceof ApiError
        ? "Couldn't parse that. Try rephrasing or simplifying."
        : 'Network error — make sure the backend is running.'
  } finally {
    parsing.value = false
  }
}

function onEdit(): void {
  result.value = null
  applyError.value = ''
  confirmingApply.value = false
}

async function onApplyConfirm(): Promise<void> {
  if (!result.value) return
  applyError.value = ''
  applying.value = true
  try {
    await replace(
      props.parentType,
      props.parentId,
      result.value.serving_times,
    )
    toast.success('Serving times updated')
    result.value = null
    confirmingApply.value = false
    promptText.value = ''
    emit('applied')
  } catch (e) {
    if (e instanceof ApiError) {
      const firstFieldErr = e.errors ? Object.values(e.errors)[0]?.[0] : undefined
      applyError.value = firstFieldErr ?? e.message
    } else {
      applyError.value = 'Network error'
    }
  } finally {
    applying.value = false
  }
}
</script>

<template>
  <div class="hoursai">
    <!-- Prompt input (default + after Edit) -->
    <section v-if="!result" class="card card--prompt">
      <header class="card__head">
        <h3 class="card__title">
          <span class="card__sparkle" aria-hidden="true">✦</span>
          Describe your serving times in plain English
        </h3>
        <p class="card__hint">DeepSeek will turn it into a structured preview before anything is saved.</p>
      </header>

      <AppTextarea
        v-model="promptText"
        :placeholder="placeholder"
        :rows="4"
        :maxlength="1000"
        :disabled="parsing"
      />

      <div class="card__actions">
        <AppButton variant="primary" :loading="parsing" :disabled="!canParse" @click="onParse">
          Parse with HoursAI
        </AppButton>
      </div>

      <p v-if="parseError" class="banner banner--error">{{ parseError }}</p>
    </section>

    <!-- Clarification needed (defensive — backend doesn't currently emit this) -->
    <section v-else-if="result.clarification_needed" class="banner banner--warning">
      <strong>Need a bit more detail.</strong>
      <p>Try rephrasing the prompt with specific days or times.</p>
      <AppButton variant="secondary" size="sm" @click="onEdit">Edit prompt</AppButton>
    </section>

    <!-- Preview + apply -->
    <template v-else>
      <section class="card card--preview">
        <header class="card__head">
          <h3 class="card__title">
            <span class="card__sparkle" aria-hidden="true">✦</span>
            Preview — what we understood
          </h3>
          <p class="card__hint">
            {{ result.serving_times.length }} slot{{ result.serving_times.length === 1 ? '' : 's' }}
            · Nothing has been saved yet.
          </p>
        </header>
        <ServingTimesPreview :items="result.serving_times" />
      </section>

      <p v-if="applyError" class="banner banner--error">{{ applyError }}</p>

      <ConfirmDelete
        v-if="confirmingApply"
        message="This replaces ALL existing serving times for this entity. Proceed?"
        confirm-label="Replace all"
        :loading="applying"
        @confirm="onApplyConfirm"
        @cancel="confirmingApply = false"
      />

      <div v-else class="apply-row">
        <AppButton variant="secondary" :disabled="applying" @click="onEdit">Edit prompt</AppButton>
        <AppButton variant="primary" :disabled="!canApply" @click="confirmingApply = true">
          Apply
        </AppButton>
      </div>
    </template>
  </div>
</template>

<style scoped>
.hoursai {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}

.card--prompt {
  background: linear-gradient(
    180deg,
    var(--primary-accent-04-transparent) 0%,
    var(--white) 100%
  );
}

.card__head { display: flex; flex-direction: column; gap: 4px; }
.card__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  color: var(--grayscale-100);
}
.card__sparkle {
  color: var(--primary-accent-100);
  font-size: 18px;
}
.card__hint {
  color: var(--grayscale-60);
  font-size: 13px;
}

.card__actions {
  display: flex;
  justify-content: flex-end;
}

.apply-row {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.banner {
  margin: 0;
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-start;
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
.banner--warning p { color: var(--grayscale-80); }
</style>
