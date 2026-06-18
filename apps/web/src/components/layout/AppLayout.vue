<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
  error?: string | null
}>()
</script>

<template>
  <main class="app-shell">
    <header class="route-header">
      <div>
        <h1>{{ title }}</h1>
        <p v-if="subtitle">{{ subtitle }}</p>
      </div>
      <div class="route-actions">
        <slot name="actions" />
      </div>
    </header>

    <p v-if="error" class="toast" role="alert">{{ error }}</p>

    <slot />
  </main>
</template>

<style scoped>
.route-header {
  min-height: 3.4rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.route-header p {
  color: var(--muted);
}

.route-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.toast {
  position: fixed;
  right: var(--page-gutter);
  top: 0.75rem;
  z-index: 20;
  width: min(420px, calc(100vw - var(--page-gutter) - var(--page-gutter)));
  border: 1px solid color-mix(in srgb, var(--danger), #fff 65%);
  border-radius: 8px;
  padding: 0.65rem 0.75rem;
  background: color-mix(in srgb, var(--danger), #fff 92%);
  color: var(--danger);
  box-shadow: 0 10px 30px rgba(40, 30, 20, 0.14);
}

@media (max-width: 980px) {
  .route-header {
    display: grid;
  }
}

@media (max-width: 760px) {
  .route-actions {
    display: grid;
    grid-template-columns: 1fr;
  }
}
</style>
