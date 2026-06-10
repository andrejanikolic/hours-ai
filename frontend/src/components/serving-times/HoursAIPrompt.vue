<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useServingTimes } from '../../composables/useServingTimes'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { ParentType, ParseResult, ServingTime } from '../../types'
import AppButton from '../shared/AppButton.vue'
import AppTextarea from '../shared/AppTextarea.vue'
import VoiceButton from '../shared/VoiceButton.vue'
import ConfirmDelete from '../shared/ConfirmDelete.vue'
import ServingTimesDiff from './ServingTimesDiff.vue'

const props = defineProps<{
  parentType: ParentType
  parentId: number
  entityName?: string
}>()
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

const currentItems = ref<ServingTime[]>([])
const currentLoaded = ref(false)

const parsing = ref(false)
const parseError = ref('')
const result = ref<ParseResult | null>(null)

const applying = ref(false)
const applyError = ref('')
const confirmingApply = ref(false)

const { list, parse, replace } = useServingTimes()
const toast = useToast()

const canParse = computed(() => promptText.value.trim().length > 0 && !parsing.value)
const canApply = computed(() => !!result.value && result.value.serving_times.length > 0)
const placeholder = computed(() => `e.g. "${PROMPT_EXAMPLES[placeholderIdx.value]}"`)

onMounted(async () => {
  placeholderTimer = window.setInterval(() => {
    if (!promptText.value) {
      placeholderIdx.value = (placeholderIdx.value + 1) % PROMPT_EXAMPLES.length
    }
  }, 4000)

  try {
    currentItems.value = await list(props.parentType, props.parentId)
  } catch {
    /* Diff still works with empty 'current' — every proposed slot shows as 'added'. */
  } finally {
    currentLoaded.value = true
  }
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
      props.entityName,
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
        <p class="card__hint">HoursAI will turn it into a structured preview before anything is saved.</p>
      </header>

      <AppTextarea
        v-model="promptText"
        test-id="hours-prompt-input"
        :placeholder="placeholder"
        :rows="4"
        :maxlength="1000"
        :disabled="parsing"
      />

      <div class="card__actions">
        <VoiceButton v-model="promptText" :disabled="parsing" class="card__voice" />
        <AppButton data-testid="hours-parse-btn" variant="primary" :loading="parsing" :disabled="!canParse" @click="onParse">
          Parse with HoursAI
        </AppButton>
      </div>

      <p v-if="parseError" data-testid="hours-error" class="banner banner--error">{{ parseError }}</p>
    </section>

    <!-- Clarification needed -->
    <section v-else-if="result.clarification_needed" data-testid="hours-clarification" class="banner banner--warning">
      <strong>Need a bit more detail.</strong>
      <p>{{ result.clarification_message ?? 'Try rephrasing with specific days or times.' }}</p>
      <AppButton data-testid="hours-edit-btn" variant="secondary" size="sm" @click="onEdit">Edit prompt</AppButton>
    </section>

    <!-- Preview + apply -->
    <template v-else>
      <section data-testid="hours-preview" class="card card--preview">
        <header class="card__head">
          <h3 class="card__title">
            <span class="card__sparkle" aria-hidden="true">✦</span>
            Preview — what will change
          </h3>
          <p class="card__hint">
            Nothing has been saved yet. Review the diff below before applying.
          </p>
        </header>
        <ServingTimesDiff :current="currentItems" :proposed="result.serving_times" />
      </section>

      <p v-if="applyError" data-testid="hours-error" class="banner banner--error">{{ applyError }}</p>

      <ConfirmDelete
        v-if="confirmingApply"
        message="This replaces ALL existing serving times for this entity. Proceed?"
        confirm-label="Replace all"
        :loading="applying"
        @confirm="onApplyConfirm"
        @cancel="confirmingApply = false"
      />

      <div v-else class="apply-row">
        <AppButton data-testid="hours-edit-btn" variant="secondary" :disabled="applying" @click="onEdit">Edit prompt</AppButton>
        <AppButton data-testid="hours-apply-btn" variant="primary" :disabled="!canApply" @click="confirmingApply = true">
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
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}
/* Voice button sits at the far left of the actions row; Parse stays right. */
.card__voice { margin-right: auto; }

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
