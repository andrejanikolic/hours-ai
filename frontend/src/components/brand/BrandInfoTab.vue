<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useBrands } from '../../composables/useBrands'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { Brand } from '../../types'
import AppInput from '../shared/AppInput.vue'
import AppButton from '../shared/AppButton.vue'

const props = defineProps<{ brand: Brand }>()
const emit = defineEmits<{ (e: 'updated', brand: Brand): void }>()

const name = ref(props.brand.name)
const error = ref('')
const saving = ref(false)

watch(
  () => props.brand.name,
  (v) => {
    name.value = v
  },
)

const { update } = useBrands()
const toast = useToast()

const isDirty = computed(() => name.value.trim() !== props.brand.name)
const canSave = computed(() => isDirty.value && name.value.trim().length > 0)

async function save(): Promise<void> {
  if (!canSave.value) return
  error.value = ''
  saving.value = true
  try {
    const updated = await update(props.brand.id, { name: name.value.trim() })
    emit('updated', updated)
    toast.success('Brand updated')
  } catch (e) {
    error.value =
      e instanceof ApiError ? e.firstFieldError('name') ?? e.message : 'Network error'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="form" @submit.prevent="save">
    <AppInput v-model="name" label="Brand name" :error="error" required />
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

.form__actions {
  display: flex;
  justify-content: flex-end;
}
</style>
