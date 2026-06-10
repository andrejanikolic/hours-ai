<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMenus, type MenuUpdateBody } from '../../composables/useMenus'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { Menu } from '../../types'
import AppInput from '../shared/AppInput.vue'
import AppTextarea from '../shared/AppTextarea.vue'
import AppToggle from '../shared/AppToggle.vue'
import AppButton from '../shared/AppButton.vue'

const props = defineProps<{ brandId: number; menu: Menu }>()
const emit = defineEmits<{ (e: 'updated', menu: Menu): void }>()

const name = ref(props.menu.name)
const description = ref(props.menu.description ?? '')
const active = ref(props.menu.active)

const error = ref('')
const fieldErrors = ref<Record<string, string>>({})
const saving = ref(false)

watch(
  () => props.menu,
  (m) => {
    name.value = m.name
    description.value = m.description ?? ''
    active.value = m.active
  },
)

const { update } = useMenus()
const toast = useToast()

const isDirty = computed(
  () =>
    name.value !== props.menu.name ||
    description.value !== (props.menu.description ?? '') ||
    active.value !== props.menu.active,
)
const canSave = computed(() => isDirty.value && name.value.trim().length > 0)

async function save(): Promise<void> {
  if (!canSave.value) return
  error.value = ''
  fieldErrors.value = {}
  saving.value = true
  try {
    const patch: MenuUpdateBody = {}
    if (name.value !== props.menu.name) patch.name = name.value.trim()
    if (description.value !== (props.menu.description ?? '')) {
      patch.description = description.value.trim() || null
    }
    if (active.value !== props.menu.active) patch.active = active.value
    const updated = await update(props.brandId, props.menu.id, patch)
    emit('updated', updated)
    toast.success('Menu updated')
  } catch (e) {
    if (e instanceof ApiError && e.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(e.errors).map(([k, v]) => [k, v[0]]),
      )
    } else {
      error.value = e instanceof ApiError ? e.message : 'Network error'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="form" @submit.prevent="save">
    <AppInput v-model="name" label="Menu name" :error="fieldErrors.name" required />
    <AppTextarea
      v-model="description"
      label="Description"
      placeholder="Optional"
      :rows="3"
      :error="fieldErrors.description"
    />
    <div class="form__row">
      <AppToggle v-model="active" :label="active ? 'Active' : 'Inactive'" />
    </div>

    <p v-if="error" class="form__error">{{ error }}</p>

    <div class="form__actions">
      <AppButton variant="primary" type="submit" :loading="saving" :disabled="!canSave">
        Save changes
      </AppButton>
    </div>
  </form>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  max-width: 480px;
}
.form__row { display: flex; align-items: center; }
.form__actions { display: flex; justify-content: flex-end; }
.form__error { color: var(--status-error); font-size: 13px; margin: 0; }
</style>
