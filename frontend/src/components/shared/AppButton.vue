<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
    size?: 'md' | 'sm'
    loading?: boolean
    disabled?: boolean
    type?: 'button' | 'submit'
  }>(),
  { variant: 'primary', size: 'md', type: 'button' },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="['btn', `btn--${variant}`, `btn--${size}`, { 'btn--loading': loading }]"
  >
    <span v-if="loading" class="btn__spinner" aria-hidden="true"></span>
    <span class="btn__content" :class="{ 'btn__content--hidden': loading }">
      <slot name="icon" />
      <slot />
    </span>
  </button>
</template>

<style scoped>
.btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: var(--radius-sm);
  font-weight: var(--font-weight-semibold);
  font-size: 14px;
  transition: background-color 0.15s, color 0.15s, border-color 0.15s;
  white-space: nowrap;
}
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.btn--md { height: 36px; padding: 0 16px; }
.btn--sm { height: 28px; padding: 0 12px; font-size: 13px; }

.btn--primary { background: var(--primary-accent-100); color: var(--white); }
.btn--primary:not(:disabled):hover { background: var(--primary-accent-80); }

.btn--secondary {
  background: var(--white);
  color: var(--grayscale-100);
  box-shadow: inset 0 0 0 1px var(--grayscale-20);
}
.btn--secondary:not(:disabled):hover {
  background: var(--primary-accent-05);
  box-shadow: inset 0 0 0 1px var(--primary-accent-40);
}

.btn--ghost { background: transparent; color: var(--primary-accent-100); }
.btn--ghost:not(:disabled):hover { background: var(--transparent-05); }

.btn--danger { background: transparent; color: var(--status-error); }
.btn--danger:not(:disabled):hover { background: rgba(255, 59, 48, 0.08); }

.btn__content { display: inline-flex; align-items: center; gap: 6px; }
.btn__content--hidden { visibility: hidden; }

.btn__spinner {
  position: absolute;
  width: 14px; height: 14px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: btn-spin 0.7s linear infinite;
}
@keyframes btn-spin { to { transform: rotate(360deg); } }
</style>
