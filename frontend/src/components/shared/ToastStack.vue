<script setup lang="ts">
import { useToast } from '../../composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <div class="toast-stack" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="toast">
      <div
        v-for="t in toasts"
        :key="t.id"
        :class="['toast', `toast--${t.kind}`]"
        role="status"
      >
        <span class="toast__icon" aria-hidden="true">
          <svg v-if="t.kind === 'success'" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          <svg v-else-if="t.kind === 'error'" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
        </span>
        <span class="toast__message">{{ t.message }}</span>
        <button
          type="button"
          class="toast__close"
          aria-label="Dismiss"
          @click="dismiss(t.id)"
        >
          ×
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-stack {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 100;
  display: flex;
  flex-direction: column;
  gap: 8px;
  pointer-events: none;
}

.toast {
  pointer-events: auto;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 260px;
  max-width: 380px;
  padding: 10px 12px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-modal);
  font-size: 13px;
  color: var(--grayscale-100);
}

.toast__icon {
  display: inline-flex;
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.toast--success .toast__icon { background: var(--status-success-15); color: var(--status-success); }
.toast--error   .toast__icon { background: rgba(255, 59, 48, 0.12); color: var(--status-error); }
.toast--info    .toast__icon { background: var(--status-info-15); color: var(--status-info); }

.toast__message {
  flex: 1;
  min-width: 0;
  word-wrap: break-word;
}

.toast__close {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  font-size: 18px;
  line-height: 1;
  color: var(--grayscale-50);
  border-radius: var(--radius-sm);
  transition: background-color 0.12s, color 0.12s;
}
.toast__close:hover { background: var(--transparent-05); color: var(--grayscale-100); }

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-leave-active {
  position: absolute;
  right: 0;
}

@media (max-width: 640px) {
  .toast-stack {
    left: 12px;
    right: 12px;
    bottom: 12px;
  }
  .toast {
    min-width: 0;
    max-width: none;
    width: 100%;
  }
  .toast-enter-from,
  .toast-leave-to {
    transform: translateY(12px);
  }
}
</style>
