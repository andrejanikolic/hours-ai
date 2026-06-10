<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue: string | number | null | undefined
    label?: string
    placeholder?: string
    error?: string
    disabled?: boolean
    type?: 'text' | 'date' | 'time' | 'number' | 'email'
    required?: boolean
    id?: string
  }>(),
  { type: 'text' },
)

defineEmits<{ (e: 'update:modelValue', value: string): void }>()
</script>

<template>
  <label class="field" :class="{ 'field--error': !!error }">
    <span v-if="label" class="field__label">
      {{ label }}<span v-if="required" class="field__required">*</span>
    </span>
    <input
      :id="id"
      class="field__input"
      :type="type"
      :value="modelValue ?? ''"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <span v-if="error" class="field__error">{{ error }}</span>
  </label>
</template>

<style scoped>
.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
}
.field__label {
  color: var(--grayscale-80);
  font-size: 13px;
  font-weight: var(--font-weight-semibold);
}
.field__required { color: var(--status-error); margin-left: 2px; }

.field__input {
  height: 36px;
  padding: 0 12px;
  background: var(--white);
  border: 1px solid var(--grayscale-20);
  border-radius: var(--radius-sm);
  color: var(--grayscale-100);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.field__input::placeholder { color: var(--grayscale-40); }
.field__input:hover:not(:disabled) { border-color: var(--primary-accent-40); }
.field__input:focus { border-color: var(--primary-accent-100); box-shadow: var(--focus-ring); }
.field__input:disabled { background: var(--grayscale-05); cursor: not-allowed; }

.field--error .field__input {
  border-color: var(--status-error);
}
.field--error .field__input:focus {
  box-shadow: var(--error-ring);
}
.field__error { color: var(--status-error); font-size: 12px; }
</style>
