<script setup lang="ts">
import { watch } from 'vue'
import { useSpeechInput } from '../../composables/useSpeechInput'

const props = defineProps<{
  modelValue: string
  disabled?: boolean
  /** BCP-47 language for dictation. Defaults to en-US. */
  lang?: string
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

// Text already present when the user pressed Speak; new speech is appended after.
let base = ''

function onResult(final: string, interim: string): void {
  emit('update:modelValue', base + (final + interim).trimStart())
}

const speech = useSpeechInput({ lang: props.lang ?? 'en-US', onresult: onResult })

function toggle(): void {
  if (speech.listening.value) {
    speech.stop()
    return
  }
  const cur = props.modelValue?.trimEnd() ?? ''
  base = cur ? `${cur} ` : ''
  speech.start()
}

watch(
  () => props.disabled,
  (d) => {
    if (d && speech.listening.value) speech.stop()
  },
)
</script>

<template>
  <div v-if="speech.supported" class="voice">
    <button
      type="button"
      class="voice__btn"
      :class="{ 'voice__btn--on': speech.listening.value }"
      :disabled="disabled"
      :aria-pressed="speech.listening.value"
      @click="toggle"
    >
      <span v-if="speech.listening.value" class="voice__eq" aria-hidden="true">
        <i></i><i></i><i></i><i></i>
      </span>
      <svg v-else class="voice__mic" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
        <rect x="9" y="2" width="6" height="11" rx="3" fill="currentColor" />
        <path
          d="M5 11a7 7 0 0 0 14 0"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
        />
        <line x1="12" y1="18" x2="12" y2="22" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
      </svg>
      <span v-if="speech.listening.value" class="voice__label">
        {{ speech.listening.value ? 'Listening… tap to stop' : '' }}
      </span>
    </button>

    <span v-if="speech.error.value && !speech.listening.value" class="voice__error">
      {{ speech.error.value }}
    </span>
  </div>
</template>

<style scoped>
.voice {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.voice__btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  padding: 0 14px 0 12px;
  border-radius: 999px;
  border: 1px solid var(--primary-accent-15);
  background: linear-gradient(180deg, var(--primary-accent-05) 0%, var(--white) 100%);
  color: var(--primary-accent-100);
  font-size: 13px;
  font-weight: var(--font-weight-semibold);
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s, background-color 0.15s;
}
.voice__btn:hover:not(:disabled) {
  border-color: var(--primary-accent-40);
  box-shadow: 0 2px 10px rgba(10, 52, 80, 0.14);
  transform: translateY(-1px);
}
.voice__btn:active:not(:disabled) { transform: translateY(0); }
.voice__btn:disabled { opacity: 0.5; cursor: not-allowed; }

.voice__mic { flex-shrink: 0; }

/* Recording state — bold red with a soft pulsing glow. */
.voice__btn--on {
  border-color: transparent;
  background: linear-gradient(180deg, #ff5247 0%, #ec3b30 100%);
  color: var(--white);
  animation: voice-glow 1.6s ease-in-out infinite;
}
.voice__btn--on:hover:not(:disabled) {
  box-shadow: 0 4px 16px rgba(236, 59, 48, 0.4);
  transform: translateY(-1px);
}
@keyframes voice-glow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(236, 59, 48, 0.5); }
  50% { box-shadow: 0 0 0 7px rgba(236, 59, 48, 0); }
}

/* Live equalizer shown while listening. */
.voice__eq {
  display: inline-flex;
  align-items: flex-end;
  gap: 2px;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}
.voice__eq i {
  width: 3px;
  background: currentColor;
  border-radius: 2px;
  animation: voice-eq 0.9s ease-in-out infinite;
}
.voice__eq i:nth-child(1) { animation-delay: 0s; }
.voice__eq i:nth-child(2) { animation-delay: 0.15s; }
.voice__eq i:nth-child(3) { animation-delay: 0.3s; }
.voice__eq i:nth-child(4) { animation-delay: 0.45s; }
@keyframes voice-eq {
  0%, 100% { height: 4px; }
  50% { height: 15px; }
}

.voice__error { color: var(--status-error); font-size: 12px; }
</style>
