<script setup lang="ts">
import { titleCase } from '../../domain/text'

const props = defineProps<{
  id: string
  label: string
  modelValue: string
  error?: string
  titleCase?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

function commit() {
  if (props.titleCase) {
    emit('update:modelValue', titleCase(props.modelValue))
  }
}
</script>

<template>
  <label class="field" :for="id">
    <span>{{ label }}</span>
    <input
      :id="id"
      type="text"
      :class="{ 'title-case': titleCase }"
      :aria-invalid="error === undefined ? undefined : true"
      :aria-describedby="`${id}-error`"
      :value="modelValue"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      @blur="commit"
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

.title-case {
  text-transform: capitalize;
}

.field-error {
  min-height: 1rem;
  color: var(--danger);
  font-size: 0.72rem;
  line-height: 1;
}
</style>
