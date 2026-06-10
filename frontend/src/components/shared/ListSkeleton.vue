<script setup lang="ts">
withDefaults(
  defineProps<{
    rows?: number
    rowHeight?: string
    inCard?: boolean
  }>(),
  { rows: 3, rowHeight: '52px', inCard: true },
)
</script>

<template>
  <div class="skel" :class="{ 'skel--card': inCard }">
    <div
      v-for="i in rows"
      :key="i"
      class="skel__bar"
      :style="{ height: rowHeight }"
    ></div>
  </div>
</template>

<style scoped>
.skel {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: var(--transparent-05);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.skel--card {
  background: var(--white);
  box-shadow: var(--shadow-card);
}

.skel__bar {
  background: linear-gradient(
    90deg,
    var(--grayscale-05) 25%,
    var(--grayscale-10) 50%,
    var(--grayscale-05) 75%
  );
  background-size: 200% 100%;
  animation: skel-shimmer 1.4s infinite;
}

.skel__bar + .skel__bar { border-top: 1px solid var(--transparent-05); }

@keyframes skel-shimmer {
  from { background-position: 200% 0; }
  to { background-position: -200% 0; }
}
</style>
