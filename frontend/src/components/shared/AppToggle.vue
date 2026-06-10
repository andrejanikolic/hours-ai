<script setup lang="ts">
defineProps<{
  modelValue: boolean
  label?: string
  disabled?: boolean
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void }>()

function toggle(current: boolean): void {
  emit('update:modelValue', !current)
}
</script>

<template>
  <label class="toggle" :class="{ 'toggle--on': modelValue, 'toggle--disabled': disabled }">
    <button
      type="button"
      class="toggle__switch"
      role="switch"
      :aria-checked="modelValue"
      :disabled="disabled"
      @click="toggle(modelValue)"
    >
      <span class="toggle__thumb"></span>
    </button>
    <span v-if="label" class="toggle__label">{{ label }}</span>
  </label>
</template>

<style scoped>
.toggle {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  cursor: pointer;
}
.toggle--disabled { cursor: not-allowed; opacity: 0.55; }

.toggle__switch {
  position: relative;
  width: 36px;
  height: 20px;
  border-radius: 999px;
  background: var(--grayscale-20);
  transition: background-color 0.15s;
  flex-shrink: 0;
}
.toggle__switch:disabled { cursor: not-allowed; }
.toggle__switch:focus-visible { box-shadow: var(--focus-ring); }

.toggle__thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--white);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s;
}

.toggle--on .toggle__switch { background: var(--status-success); }
.toggle--on .toggle__thumb { transform: translateX(16px); }

.toggle__label { color: var(--grayscale-100); user-select: none; }
</style>
