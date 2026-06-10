<script setup lang="ts">
import AppButton from './AppButton.vue'

withDefaults(
  defineProps<{
    saving?: boolean
    saveLabel?: string
    cancelLabel?: string
    canSave?: boolean
  }>(),
  { saveLabel: 'Save', cancelLabel: 'Cancel', canSave: true },
)

const emit = defineEmits<{
  (e: 'save'): void
  (e: 'cancel'): void
}>()

function onSubmit(): void {
  emit('save')
}
function onKey(e: KeyboardEvent): void {
  if (e.key === 'Escape') emit('cancel')
}
</script>

<template>
  <form class="inline-form" @submit.prevent="onSubmit" @keydown="onKey">
    <div class="inline-form__fields">
      <slot />
    </div>
    <div class="inline-form__actions">
      <AppButton
        variant="secondary"
        size="sm"
        type="button"
        :disabled="saving"
        @click="$emit('cancel')"
      >
        {{ cancelLabel }}
      </AppButton>
      <AppButton
        variant="primary"
        size="sm"
        type="submit"
        :loading="saving"
        :disabled="!canSave"
      >
        {{ saveLabel }}
      </AppButton>
    </div>
  </form>
</template>

<style scoped>
.inline-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: var(--primary-accent-04-transparent);
  border: 1px dashed var(--primary-accent-40);
  border-radius: var(--radius-md);
}

.inline-form__fields {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.inline-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
