import { readonly, ref } from 'vue'

export type ToastKind = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  kind: ToastKind
  message: string
}

const toasts = ref<Toast[]>([])
let _nextId = 0

function push(kind: ToastKind, message: string, durationMs = 3500): number {
  const id = ++_nextId
  toasts.value.push({ id, kind, message })
  if (durationMs > 0) {
    setTimeout(() => dismiss(id), durationMs)
  }
  return id
}

function dismiss(id: number): void {
  const i = toasts.value.findIndex((t) => t.id === id)
  if (i >= 0) toasts.value.splice(i, 1)
}

/**
 * Single global toast stack. The visible `<ToastStack>` component (added in Phase 9)
 * reads `toasts` and renders. Until then, callers can still push — they'll be
 * harmless no-ops UI-wise.
 */
export function useToast() {
  return {
    toasts: readonly(toasts),
    success: (message: string, durationMs?: number) => push('success', message, durationMs),
    error: (message: string, durationMs?: number) => push('error', message, durationMs),
    info: (message: string, durationMs?: number) => push('info', message, durationMs),
    dismiss,
  }
}
