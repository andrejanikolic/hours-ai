<script setup lang="ts">
import AppButton from './AppButton.vue'

withDefaults(
  defineProps<{
    message?: string
    confirmLabel?: string
    cancelLabel?: string
    loading?: boolean
  }>(),
  { message: 'Are you sure?', confirmLabel: 'Delete', cancelLabel: 'Cancel' },
)

defineEmits<{
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()
</script>

<template>
  <div class="confirm" role="alertdialog" aria-live="polite">
    <span class="confirm__message">{{ message }}</span>
    <div class="confirm__actions">
      <AppButton
        variant="secondary"
        size="sm"
        type="button"
        :disabled="loading"
        @click="$emit('cancel')"
      >
        {{ cancelLabel }}
      </AppButton>
      <AppButton
        variant="danger"
        size="sm"
        type="button"
        :loading="loading"
        @click="$emit('confirm')"
      >
        {{ confirmLabel }}
      </AppButton>
    </div>
  </div>
</template>

<style scoped>
.confirm {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 14px;
  background: rgba(255, 59, 48, 0.06);
  border: 1px solid rgba(255, 59, 48, 0.2);
  border-radius: var(--radius-sm);
}

.confirm__message {
  font-size: 13px;
  color: var(--grayscale-80);
}

.confirm__actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}
</style>
