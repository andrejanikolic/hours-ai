<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useVenues, type VenueUpdateBody } from '../../composables/useVenues'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { Venue } from '../../types'
import AppInput from '../shared/AppInput.vue'
import AppButton from '../shared/AppButton.vue'

const props = defineProps<{ brandId: number; venue: Venue }>()
const emit = defineEmits<{ (e: 'updated', venue: Venue): void }>()

const name = ref(props.venue.name)
const address = ref(props.venue.address ?? '')
const timezone = ref(props.venue.timezone ?? '')

const error = ref('')
const fieldErrors = ref<Record<string, string>>({})
const saving = ref(false)

watch(
  () => props.venue,
  (v) => {
    name.value = v.name
    address.value = v.address ?? ''
    timezone.value = v.timezone ?? ''
  },
)

const { update } = useVenues()
const toast = useToast()

const isDirty = computed(
  () =>
    name.value !== props.venue.name ||
    address.value !== (props.venue.address ?? '') ||
    timezone.value !== (props.venue.timezone ?? ''),
)
const canSave = computed(() => isDirty.value && name.value.trim().length > 0)

async function save(): Promise<void> {
  if (!canSave.value) return
  error.value = ''
  fieldErrors.value = {}
  saving.value = true
  try {
    const patch: VenueUpdateBody = {}
    if (name.value !== props.venue.name) patch.name = name.value.trim()
    if (address.value !== (props.venue.address ?? '')) {
      patch.address = address.value.trim() || null
    }
    if (timezone.value !== (props.venue.timezone ?? '')) {
      patch.timezone = timezone.value.trim() || null
    }
    const updated = await update(props.brandId, props.venue.id, patch)
    emit('updated', updated)
    toast.success('Venue updated')
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
    <AppInput v-model="name" label="Venue name" :error="fieldErrors.name" required />
    <AppInput v-model="address" label="Address" placeholder="e.g. 123 Main St" :error="fieldErrors.address" />
    <AppInput
      v-model="timezone"
      label="Timezone"
      placeholder="America/New_York"
      :error="fieldErrors.timezone"
    />

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
.form__actions { display: flex; justify-content: flex-end; }
.form__error { color: var(--status-error); font-size: 13px; margin: 0; }
</style>
