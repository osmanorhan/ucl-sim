<script setup lang="ts">
defineProps<{
  id: string
  label: string
  error?: string
}>()

const model = defineModel<string>({ required: true })
</script>

<template>
  <label class="field" :for="id">
    <span>{{ label }}</span>
    <input
      :id="id"
      v-model="model"
      type="text"
      :aria-invalid="error ? true : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
    >
    <span :id="`${id}-error`" class="field-error">{{ error }}</span>
  </label>
</template>

<style scoped>
.field {
  display: grid;
  gap: 0.35rem;
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 650;
}

.field-error {
  min-height: 1rem;
  color: var(--danger);
  font-size: 0.72rem;
  line-height: 1;
}
</style>
