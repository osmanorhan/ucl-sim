<script setup lang="ts">
defineProps<{
  id: string
  label: string
  modelValue: string
  error?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <label class="field" :for="id">
    <span>{{ label }}</span>
    <input
      :id="id"
      type="text"
      :aria-invalid="error === undefined ? undefined : true"
      :aria-describedby="`${id}-error`"
      :value="modelValue"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
    <span :id="`${id}-error`" class="field-error">{{ error }}</span>
  </label>
</template>
