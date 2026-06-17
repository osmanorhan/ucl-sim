<script setup lang="ts">
defineProps<{
  id: string
  label: string
  min?: number
  step?: number
  modelValue: number
  error?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()
</script>

<template>
  <label class="field" :for="id">
    <span>{{ label }}</span>
    <input
      :id="id"
      type="number"
      :min="min"
      :step="step ?? 1"
      :aria-invalid="error === undefined ? undefined : true"
      :aria-describedby="`${id}-error`"
      :value="modelValue"
      @input="emit('update:modelValue', Number(($event.target as HTMLInputElement).value))"
    >
    <span :id="`${id}-error`" class="field-error">{{ error }}</span>
  </label>
</template>
