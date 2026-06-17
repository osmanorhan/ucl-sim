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
