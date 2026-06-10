<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <section class="page">
    <h1>Menu #{{ route.params.menuId }}</h1>
    <p class="hint">Brand #{{ route.params.brandId }} · Menu detail page — coming in Phase 5.</p>
  </section>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.hint {
  color: var(--grayscale-60);
}
</style>
