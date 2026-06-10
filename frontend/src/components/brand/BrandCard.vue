<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppButton from '../shared/AppButton.vue'
import ConfirmDelete from '../shared/ConfirmDelete.vue'
import StatusDot from '../shared/StatusDot.vue'
import type { Brand } from '../../types'

const props = defineProps<{
  brand: Brand
  deleting?: boolean
}>()

const emit = defineEmits<{ (e: 'delete', id: number): void }>()

const confirming = ref(false)

function onConfirm(): void {
  emit('delete', props.brand.id)
}
</script>

<template>
  <article class="card">
    <div class="card__top">
      <RouterLink :to="`/brands/${brand.id}`" class="card__title">
        {{ brand.name }}
      </RouterLink>
      <span class="card__slug">{{ brand.slug }}</span>
    </div>

    <div v-if="!confirming" class="card__bottom">
      <StatusDot
        :status="brand.active === false ? 'inactive' : 'active'"
        :label="brand.active === false ? 'Inactive' : 'Active'"
      />
      <div class="card__actions">
        <RouterLink :to="`/brands/${brand.id}`" class="card__manage">Manage</RouterLink>
        <AppButton variant="danger" size="sm" @click="confirming = true">Delete</AppButton>
      </div>
    </div>

    <div v-else class="card__bottom card__bottom--confirm">
      <ConfirmDelete
        :message="`Delete '${brand.name}' and all its venues, menus, and serving times?`"
        :loading="deleting"
        @confirm="onConfirm"
        @cancel="confirming = false"
      />
    </div>
  </article>
</template>

<style scoped>
.card {
  display: flex;
  flex-direction: column;
  background: var(--white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  overflow: hidden;
  transition: box-shadow 0.15s, transform 0.15s;
}
.card:hover {
  box-shadow: 0 0 0 1px var(--transparent-05), 0 6px 16px var(--transparent-07);
}

.card__top {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px;
  border-bottom: 1px solid var(--transparent-07);
}

.card__title {
  font-size: 16px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-100);
}
.card__title:hover { color: var(--primary-accent-100); text-decoration: none; }

.card__slug {
  font-size: 12px;
  color: var(--grayscale-50);
  font-family: ui-monospace, monospace;
}

.card__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px 8px 16px;
  min-height: 46px;
}

.card__bottom--confirm { padding: 8px; }

.card__actions { display: flex; gap: 4px; align-items: center; }

.card__manage {
  font-size: 13px;
  font-weight: var(--font-weight-semibold);
  color: var(--primary-accent-100);
  padding: 0 8px;
}
.card__manage:hover { text-decoration: none; }
</style>
