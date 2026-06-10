<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useBrands } from '../composables/useBrands'
import { useToast } from '../composables/useToast'
import { ApiError } from '../composables/useApi'
import type { Brand } from '../types'
import PageHeader from '../components/shared/PageHeader.vue'
import AppButton from '../components/shared/AppButton.vue'
import AppInput from '../components/shared/AppInput.vue'
import InlineCreateForm from '../components/shared/InlineCreateForm.vue'
import BrandCard from '../components/brand/BrandCard.vue'
import ListSkeleton from '../components/shared/ListSkeleton.vue'

const brands = ref<Brand[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)

const showCreate = ref(false)
const newName = ref('')
const newError = ref('')
const saving = ref(false)

const deletingId = ref<number | null>(null)

const { list, create, remove } = useBrands()
const toast = useToast()

onMounted(loadBrands)

async function loadBrands(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    brands.value = await list()
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : 'Network error'
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  showCreate.value = true
  newName.value = ''
  newError.value = ''
}

function cancelCreate(): void {
  showCreate.value = false
  newName.value = ''
  newError.value = ''
}

async function saveCreate(): Promise<void> {
  newError.value = ''
  saving.value = true
  try {
    const created = await create({ name: newName.value.trim() })
    brands.value = [created, ...brands.value]
    cancelCreate()
    toast.success(`Created '${created.name}'`)
  } catch (e) {
    if (e instanceof ApiError) {
      newError.value = e.firstFieldError('name') ?? e.message
    } else {
      newError.value = 'Network error'
    }
  } finally {
    saving.value = false
  }
}

async function onDelete(id: number): Promise<void> {
  deletingId.value = id
  try {
    await remove(id)
    brands.value = brands.value.filter((b) => b.id !== id)
    toast.success('Brand deleted')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Network error')
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <PageHeader title="Brands" subtitle="Manage your brands, their venues, menus, and serving times.">
    <template #actions>
      <AppButton v-if="!showCreate" @click="openCreate">+ Add brand</AppButton>
    </template>
  </PageHeader>

  <div v-if="showCreate" class="brands__create">
    <InlineCreateForm
      :saving="saving"
      :can-save="!!newName.trim()"
      save-label="Add brand"
      @save="saveCreate"
      @cancel="cancelCreate"
    >
      <AppInput
        v-model="newName"
        label="Brand name"
        placeholder="e.g. Demo Burger"
        :error="newError"
        required
      />
    </InlineCreateForm>
  </div>

  <div v-if="loading" class="brands__grid brands__grid--loading">
    <div v-for="i in 4" :key="i" class="brands__skel">
      <ListSkeleton :rows="1" row-height="134px" />
    </div>
  </div>

  <div v-else-if="loadError" class="state state--error">
    <strong>Couldn't load brands.</strong>
    <span>{{ loadError }}</span>
    <AppButton variant="secondary" size="sm" @click="loadBrands">Try again</AppButton>
  </div>

  <div v-else-if="brands.length === 0" class="state">
    <h3>No brands yet</h3>
    <p>Add your first brand to start configuring serving times.</p>
    <AppButton v-if="!showCreate" @click="openCreate">+ Add brand</AppButton>
  </div>

  <div v-else class="brands__grid">
    <BrandCard
      v-for="b in brands"
      :key="b.id"
      :brand="b"
      :deleting="deletingId === b.id"
      @delete="onDelete"
    />
  </div>
</template>

<style scoped>
.brands__create {
  margin-bottom: 24px;
}

.brands__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.brands__grid--loading { pointer-events: none; }
.brands__skel { display: contents; }

.state {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding: 32px;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  color: var(--grayscale-80);
}
.state h3 { font-size: 16px; }
.state p { color: var(--grayscale-60); font-size: 13px; }

.state--error {
  color: var(--status-error);
  background: rgba(255, 59, 48, 0.04);
  border: 1px solid rgba(255, 59, 48, 0.2);
  box-shadow: none;
}
.state--error span { color: var(--grayscale-60); font-size: 13px; }
</style>
