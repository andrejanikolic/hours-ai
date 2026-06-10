<script setup lang="ts">
import { ref } from 'vue'
import type { ParentType } from '../../types'
import TabBar, { type Tab } from '../shared/TabBar.vue'
import ManualServingTimes from './ManualServingTimes.vue'
import HoursAIPrompt from './HoursAIPrompt.vue'

defineProps<{
  parentType: ParentType
  parentId: number
}>()

const subTabs: Tab[] = [
  { id: 'manual', label: 'Manual' },
  { id: 'hoursai', label: 'HoursAI ✦' },
]

const activeSubTab = ref('manual')

function onApplied(): void {
  activeSubTab.value = 'manual'
}
</script>

<template>
  <div class="panel">
    <TabBar v-model="activeSubTab" :tabs="subTabs" />
    <div class="panel__body">
      <ManualServingTimes
        v-if="activeSubTab === 'manual'"
        :parent-type="parentType"
        :parent-id="parentId"
      />
      <HoursAIPrompt
        v-else
        :parent-type="parentType"
        :parent-id="parentId"
        @applied="onApplied"
      />
    </div>
  </div>
</template>

<style scoped>
.panel {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.panel__body { display: flex; flex-direction: column; }
</style>
