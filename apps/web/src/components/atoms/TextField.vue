<script setup lang="ts">
import { titleCase } from '../../domain/text'

const props = defineProps<{
  id: string
  label: string
  error?: string
  titleCase?: boolean
}>()

const model = defineModel<string>({ required: true })

function commit() {
  if (props.titleCase) {
    model.value = titleCase(model.value)
  }
}
</script>

<template>
  <label class="field" :for="id">
    <span>{{ label }}</span>
    <input
      :id="id"
      v-model="model"
      type="text"
      :class="{ 'title-case': titleCase }"
      :aria-invalid="error ? true : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
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
