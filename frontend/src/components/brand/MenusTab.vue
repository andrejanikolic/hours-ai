<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMenus, type MenuCreateBody } from '../../composables/useMenus'
import { useToast } from '../../composables/useToast'
import { ApiError } from '../../composables/useApi'
import type { Menu } from '../../types'
import AppButton from '../shared/AppButton.vue'
import AppInput from '../shared/AppInput.vue'
import InlineCreateForm from '../shared/InlineCreateForm.vue'
import StatusDot from '../shared/StatusDot.vue'
import ListSkeleton from '../shared/ListSkeleton.vue'

const props = defineProps<{ brandId: number }>()

const menus = ref<Menu[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)

const showCreate = ref(false)
const form = ref({ name: '', description: '' })
const formError = ref('')
const saving = ref(false)

const { list, create } = useMenus()
const toast = useToast()

onMounted(load)

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    menus.value = await list(props.brandId)
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  form.value = { name: '', description: '' }
  formError.value = ''
  showCreate.value = true
}

function cancelCreate(): void {
  showCreate.value = false
}

async function saveCreate(): Promise<void> {
  formError.value = ''
  saving.value = true
  try {
    const body: MenuCreateBody = { name: form.value.name.trim() }
    if (form.value.description.trim()) body.description = form.value.description.trim()
    const created = await create(props.brandId, body)
    menus.value = [created, ...menus.value]
    cancelCreate()
    toast.success(`Created '${created.name}'`)
  } catch (e) {
    formError.value =
      e instanceof ApiError ? e.firstFieldError('name') ?? e.message : 'Network error'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="tab">
    <div class="tab__head">
      <h3 class="tab__title">{{ menus.length }} menu{{ menus.length === 1 ? '' : 's' }}</h3>
      <AppButton v-if="!showCreate && !loading" size="sm" @click="openCreate">+ Add menu</AppButton>
    </div>

    <InlineCreateForm
      v-if="showCreate"
      :saving="saving"
      :can-save="!!form.name.trim()"
      save-label="Add menu"
      @save="saveCreate"
      @cancel="cancelCreate"
    >
      <AppInput
        v-model="form.name"
        label="Menu name"
        placeholder="e.g. Breakfast"
        :error="formError"
        required
      />
      <AppInput v-model="form.description" label="Description" placeholder="Optional" />
    </InlineCreateForm>

    <ListSkeleton v-if="loading" :rows="3" />

    <div v-else-if="loadError" class="state state--error">
      <strong>Couldn't load menus.</strong>
      <span>{{ loadError }}</span>
      <AppButton variant="secondary" size="sm" @click="load">Try again</AppButton>
    </div>

    <div v-else-if="!menus.length" class="state">
      <p>No menus yet. Add the first one to get going.</p>
    </div>

    <ul v-else class="rows">
      <li v-for="m in menus" :key="m.id" class="rows__item">
        <RouterLink :to="`/brands/${brandId}/menus/${m.id}`" class="rows__main">
          <span class="rows__name">{{ m.name }}</span>
          <span class="rows__sub">{{ m.description || '—' }}</span>
        </RouterLink>
        <StatusDot
          :status="m.active === false ? 'inactive' : 'active'"
          :label="m.active === false ? 'Inactive' : 'Active'"
        />
      </li>
    </ul>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }

.tab__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.tab__title {
  font-size: 13px;
  color: var(--grayscale-60);
  font-weight: var(--font-weight-semibold);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.tab__loading { color: var(--grayscale-60); padding: 16px 0; }

.rows {
  list-style: none;
  margin: 0;
  padding: 0;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}

.rows__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--transparent-05);
}
.rows__item:last-child { border-bottom: none; }

.rows__main {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}
.rows__main:hover { text-decoration: none; }
.rows__main:hover .rows__name { color: var(--primary-accent-100); }

.rows__name {
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
  font-size: 14px;
}
.rows__sub { font-size: 12px; color: var(--grayscale-50); }

.state {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding: 24px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  color: var(--grayscale-60);
}
.state--error {
  background: rgba(255, 59, 48, 0.04);
  border: 1px solid rgba(255, 59, 48, 0.2);
  box-shadow: none;
  color: var(--status-error);
}
.state--error span { color: var(--grayscale-60); font-size: 13px; }
</style>
